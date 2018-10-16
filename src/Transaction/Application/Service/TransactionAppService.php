<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Service;

use Predis\Client;
use Ramsey\Uuid\Uuid;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\Log\StdoutLogger;
use RidiPay\Library\SentryHelper;
use RidiPay\Library\TimeUnitConstant;
use RidiPay\Library\ValidationTokenManager;
use RidiPay\Partner\Application\Service\PartnerAppService;
use RidiPay\Partner\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Pg\Application\Service\PgAppService;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Pg\Domain\Exception\TransactionCancellationException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\Service\PgHandlerFactory;
use RidiPay\Transaction\Application\Dto\ApproveTransactionDto;
use RidiPay\Transaction\Application\Dto\CancelTransactionDto;
use RidiPay\Transaction\Application\Dto\CreateTransactionDto;
use RidiPay\Transaction\Application\Dto\TransactionStatusDto;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;
use RidiPay\Transaction\Domain\Exception\NonexistentTransactionException;
use RidiPay\Transaction\Domain\Exception\NotReservedTransactionException;
use RidiPay\Transaction\Domain\Exception\UnvalidatedTransactionException;
use RidiPay\Transaction\Domain\Repository\TransactionHistoryRepository;
use RidiPay\Transaction\Domain\Repository\TransactionRepository;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;

class TransactionAppService
{
    private const VALIDATION_PASSWORD = 'PASSWORD';
    private const VALIDATION_PIN = 'PIN';

    /**
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param string $payment_method_uuid
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     * @param string $return_url
     * @return string
     * @throws UnauthorizedPartnerException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function reserveTransaction(
        string $partner_api_key,
        string $partner_secret_key,
        string $payment_method_uuid,
        string $partner_transaction_id,
        string $product_name,
        int $amount,
        string $return_url
    ): string {
        $partner_id = PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);
        $payment_method_id = PaymentMethodAppService::getPaymentMethodIdByUuid($payment_method_uuid);

        $reservation_id = Uuid::uuid4()->toString();
        $reservation_key = self::getReservationKey($reservation_id);

        try {
            $redis = self::getRedisClient();
            $redis->hmset(
                $reservation_key,
                [
                    'payment_method_id' => $payment_method_id,
                    'partner_id' => $partner_id,
                    'partner_transaction_id' => $partner_transaction_id,
                    'product_name' => $product_name,
                    'amount' => $amount,
                    'return_url' => $return_url,
                    'reserved_at' => (new \DateTime())->format(DATE_ATOM)
                ]
            );
            $redis->expire($reservation_key, TimeUnitConstant::SEC_IN_HOUR);
        } catch (\Throwable $t) {
            $logger = new StdoutLogger(__METHOD__);
            $logger->error($t->getMessage());

            throw $t;
        }

        return $reservation_id;
    }

    /**
     * @param string $reservation_id
     * @param int $u_idx
     * @return null|string
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws NotReservedTransactionException
     * @throws UnsupportedPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRequiredValidation(string $reservation_id, int $u_idx): ?string
    {
        $reserved_transaction = self::getReservedTransaction($reservation_id);

        $amount = intval($reserved_transaction['amount']);
        if ($amount > 100000) {
            return self::VALIDATION_PASSWORD;
        }

        $user = UserAppService::getUserInformation($u_idx);
        if ($user->is_using_onetouch_pay) {
            return null;
        }
        if ($user->has_pin) {
            return self::VALIDATION_PIN;
        } else {
            return self::VALIDATION_PASSWORD;
        }
    }

    /**
     * @param string $reservation_id
     * @return string
     * @throws \Exception
     */
    public static function generateValidationToken(string $reservation_id): string
    {
        $reservation_key = self::getReservationKey($reservation_id);

        return ValidationTokenManager::generate($reservation_key, 5 * TimeUnitConstant::SEC_IN_MINUTE);
    }

    /**
     * @param int $u_idx
     * @param string $reservation_id
     * @param string $validation_token
     * @return CreateTransactionDto
     * @throws NotReservedTransactionException
     * @throws UnvalidatedTransactionException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function createTransaction(
        int $u_idx,
        string $reservation_id,
        string $validation_token
    ): CreateTransactionDto {
        $reserved_transaction = self::getReservedTransaction($reservation_id, $validation_token);
        $pg = PgAppService::getActivePg();

        try {
            $transaction = new TransactionEntity(
                $u_idx,
                intval($reserved_transaction['payment_method_id']),
                $pg->id,
                intval($reserved_transaction['partner_id']),
                $reserved_transaction['partner_transaction_id'],
                $reserved_transaction['product_name'],
                intval($reserved_transaction['amount']),
                \DateTime::createFromFormat(DATE_ATOM, $reserved_transaction['reserved_at'])
            );
            TransactionRepository::getRepository()->save($transaction);
        } catch (\Throwable $t) {
            $logger = new StdoutLogger(__METHOD__);
            $logger->error($t->getMessage());

            throw $t;
        }

        ValidationTokenManager::invalidate(self::getReservationKey($reservation_id));

        return new CreateTransactionDto(
            $transaction->getUuid()->toString(),
            $reserved_transaction['return_url']
        );
    }

    /**
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param string $transaction_id
     * @return ApproveTransactionDto
     * @throws NonexistentTransactionException
     * @throws TransactionApprovalException
     * @throws UnauthorizedPartnerException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function approveTransaction(
        string $partner_api_key,
        string $partner_secret_key,
        string $transaction_id
    ): ApproveTransactionDto {
        PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);

        $transaction = self::getTransaction($transaction_id);

        $pg = PgAppService::getPgById($transaction->getPgId());
        $pg_handler = PgHandlerFactory::create($pg->name);
        $response = $pg_handler->approveTransaction($transaction);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $transaction->approve($response->getPgTransactionId());
            TransactionRepository::getRepository()->save($transaction);

            $transaction_history = TransactionHistoryEntity::createApproveHistory(
                $transaction,
                $response->isSuccess(),
                $response->getResponseCode(),
                $response->getResponseMessage()
            );
            TransactionHistoryRepository::getRepository()->save($transaction_history);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            $logger = new StdoutLogger(__METHOD__);
            $logger->error($t->getMessage());

            // 결제 승인 건 취소 처리
            $cancel_reason = 'RIDI Pay 후속 승인 처리 중 오류 발생';
            $cancel_transaction_response = $pg_handler->cancelTransaction($transaction_id, $cancel_reason);
            if (!$cancel_transaction_response->isSuccess()) {
                $message = 'RIDI Pay 후속 승인 처리 중 오류 발생으로 인한 결제 취소 중 오류 발생';

                $data = [
                    'extra' => [
                        'transaction_id' => $transaction_id,
                        'response_code' => $cancel_transaction_response->getResponseCode(),
                        'response_message' => $cancel_transaction_response->getResponseMessage()
                    ]
                ];
                SentryHelper::captureMessage($message, [], $data, true);
            }

            throw $t;
        }

        return new ApproveTransactionDto($transaction);
    }

    /**
     * @param int $u_idx
     * @param int $payment_method_id
     * @param int $partner_id
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     * @param \DateTime $subscribed_at
     * @return ApproveTransactionDto
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function approveTransactionBySubscription(
        int $u_idx,
        int $payment_method_id,
        int $partner_id,
        string $partner_transaction_id,
        string $product_name,
        int $amount,
        \DateTime $subscribed_at
    ): ApproveTransactionDto {
        $pg = PgAppService::getActivePg();
        $pg_handler = PgHandlerFactory::create($pg->name);

        $transaction = new TransactionEntity(
            $u_idx,
            $payment_method_id,
            $pg->id,
            $partner_id,
            $partner_transaction_id,
            $product_name,
            $amount,
            $subscribed_at
        );

        try {
            $response = $pg_handler->approveTransaction($transaction);

            $transaction->approve($response->getPgTransactionId());
            TransactionRepository::getRepository()->save($transaction);

            $transaction_history = TransactionHistoryEntity::createApproveHistory(
                $transaction,
                $response->isSuccess(),
                $response->getResponseCode(),
                $response->getResponseMessage()
            );
            TransactionHistoryRepository::getRepository()->save($transaction_history);
        } catch (\Throwable $t) {
            $logger = new StdoutLogger(__METHOD__);
            $logger->error($t->getMessage());

            // 결제 승인 건 취소 처리
            $cancel_reason = 'RIDI Pay 정기 결제 승인 처리 중 오류 발생';
            $cancel_transaction_response = $pg_handler->cancelTransaction(
                $transaction->getPgTransactionId(),
                $cancel_reason
            );
            if (!$cancel_transaction_response->isSuccess()) {
                $message = 'RIDI Pay 정기 결제 승인 처리 중 오류 발생으로 인한 결제 취소 중 오류 발생';

                $data = [
                    'extra' => [
                        'transaction_id' => $transaction->getPgTransactionId(),
                        'response_code' => $cancel_transaction_response->getResponseCode(),
                        'response_message' => $cancel_transaction_response->getResponseMessage()
                    ]
                ];
                SentryHelper::captureMessage($message, [], $data, true);
            }

            throw $t;
        }

        return new ApproveTransactionDto($transaction);
    }

    /**
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param string $transaction_id
     * @return CancelTransactionDto
     * @throws NonexistentTransactionException
     * @throws TransactionCancellationException
     * @throws UnauthorizedPartnerException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function cancelTransaction(
        string $partner_api_key,
        string $partner_secret_key,
        string $transaction_id
    ): CancelTransactionDto {
        PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);

        $transaction = self::getTransaction($transaction_id);

        $pg = PgAppService::getPgById($transaction->getPgId());
        $pg_handler = PgHandlerFactory::create($pg->name);
        $cancel_reason = '고객 결제 취소';
        $response = $pg_handler->cancelTransaction($transaction->getPgTransactionId(), $cancel_reason);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $transaction->cancel();
            TransactionRepository::getRepository()->save($transaction);

            $transaction_history = TransactionHistoryEntity::createCancelHistory(
                $transaction,
                $response->isSuccess(),
                $response->getResponseCode(),
                $response->getResponseMessage()
            );
            TransactionHistoryRepository::getRepository()->save($transaction_history);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            $logger = new StdoutLogger(__METHOD__);
            $logger->error($t->getMessage());

            throw $t;
        }

        return new CancelTransactionDto($transaction);
    }

    /**
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param string $transaction_id
     * @return TransactionStatusDto
     * @throws NonexistentTransactionException
     * @throws UnauthorizedPartnerException
     * @throws UnregisteredPaymentMethodException
     * @throws UnsupportedPaymentMethodException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getTransactionStatus(
        string $partner_api_key,
        string $partner_secret_key,
        string $transaction_id
    ): TransactionStatusDto {
        PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);

        return new TransactionStatusDto(self::getTransaction($transaction_id));
    }

    /**
     * @param string $reservation_id
     * @param null|string $validation_token
     * @return array
     * @throws NotReservedTransactionException
     * @throws UnvalidatedTransactionException
     */
    private static function getReservedTransaction(string $reservation_id, ?string $validation_token = null): array
    {
        $reservation_key = self::getReservationKey($reservation_id);

        $redis = self::getRedisClient();
        $reserved_transaction = $redis->hgetall($reservation_key);
        if (empty($reserved_transaction)) {
            throw new NotReservedTransactionException();
        }
        if (isset($reserved_transaction['validation_token'])
            && ($reserved_transaction['validation_token'] !== $validation_token)
        ) {
            throw new UnvalidatedTransactionException();
        }

        return $reserved_transaction;
    }

    /**
     * @param string $reservation_id
     * @return string
     */
    private static function getReservationKey(string $reservation_id): string
    {
        return "reservation:${reservation_id}";
    }

    /**
     * @return Client
     */
    private static function getRedisClient(): Client
    {
        return new Client(['host' => getenv('REDIS_HOST')]);
    }

    /**
     * @param string $transaction_id
     * @return TransactionEntity
     * @throws NonexistentTransactionException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private static function getTransaction(string $transaction_id): TransactionEntity
    {
        $transaction = TransactionRepository::getRepository()->findOneByUuid(Uuid::fromString($transaction_id));
        if (is_null($transaction)) {
            throw new NonexistentTransactionException();
        }

        return $transaction;
    }
}
