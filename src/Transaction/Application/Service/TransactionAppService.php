<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Service;

use Predis\Client;
use Ramsey\Uuid\Uuid;
use RidiPay\Kernel;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\Pg\Kcp\Order;
use RidiPay\Library\Pg\Kcp\UnderMinimumPaymentAmountException;
use RidiPay\Library\SentryHelper;
use RidiPay\Library\TimeUnitConstant;
use RidiPay\Library\Validation\ApiSecret;
use RidiPay\Library\ValidationTokenManager;
use RidiPay\Partner\Application\Service\PartnerAppService;
use RidiPay\Partner\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Pg\Application\Service\PgAppService;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Pg\Domain\Exception\TransactionCancellationException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\Service\Buyer;
use RidiPay\Pg\Domain\Service\PgHandlerFactory;
use RidiPay\Transaction\Application\Dto\ApproveTransactionDto;
use RidiPay\Transaction\Application\Dto\CancelTransactionDto;
use RidiPay\Transaction\Application\Dto\CreateTransactionDto;
use RidiPay\Transaction\Application\Dto\TransactionStatusDto;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;
use RidiPay\Transaction\Domain\Exception\AlreadyApprovedTransactionException;
use RidiPay\Transaction\Domain\Exception\AlreadyCancelledTransactionException;
use RidiPay\Transaction\Domain\Exception\NotFoundTransactionException;
use RidiPay\Transaction\Domain\Exception\NotReservedTransactionException;
use RidiPay\Transaction\Domain\Repository\TransactionHistoryRepository;
use RidiPay\Transaction\Domain\Repository\TransactionRepository;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Exception\DeletedPaymentMethodException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;

class TransactionAppService
{
    /**
     * @param ApiSecret $partner_api_secret
     * @param string $payment_method_uuid
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     * @param string $return_url
     * @return string
     * @throws DeletedPaymentMethodException
     * @throws UnauthorizedPartnerException
     * @throws UnderMinimumPaymentAmountException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function reserveTransaction(
        ApiSecret $partner_api_secret,
        string $payment_method_uuid,
        string $partner_transaction_id,
        string $product_name,
        int $amount,
        string $return_url
    ): string {
        $partner_id = PartnerAppService::validatePartner(
            $partner_api_secret->getApiKey(),
            $partner_api_secret->getSecretKey()
        );
        $payment_method_id = PaymentMethodAppService::getPaymentMethodIdByUuid($payment_method_uuid);
        if ($amount < Order::GOOD_PRICE_KRW_MIN) {
            throw new UnderMinimumPaymentAmountException();
        }

        $reservation_id = Uuid::uuid4()->toString();
        $reservation_key = self::getReservationKey($reservation_id);

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

        return $reservation_id;
    }

    /**
     * @param string $reservation_id
     * @param int $u_idx
     * @return bool
     * @throws DeletedPaymentMethodException
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws NotReservedTransactionException
     * @throws UnregisteredPaymentMethodException
     * @throws UnsupportedPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function isPinValidationRequired(string $reservation_id, int $u_idx): bool
    {
        $reserved_transaction = self::getReservedTransaction($reservation_id, $u_idx);

        $amount = intval($reserved_transaction['amount']);
        $user = UserAppService::getUserInformation($u_idx);
        if ($user->is_using_onetouch_pay && $amount < 100000) {
            return false;
        }

        return true;
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
     * @return CreateTransactionDto
     * @throws DeletedPaymentMethodException
     * @throws NotReservedTransactionException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function createTransaction(int $u_idx, string $reservation_id): CreateTransactionDto
    {
        $reserved_transaction = self::getReservedTransaction($reservation_id, $u_idx);
        $pg = PgAppService::getActivePg();

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

        return new CreateTransactionDto(
            $transaction->getUuid()->toString(),
            $reserved_transaction['return_url']
        );
    }

    /**
     * @param ApiSecret $partner_api_secret
     * @param string $transaction_id
     * @param string $buyer_id
     * @param string $buyer_name
     * @param string $buyer_email
     * @return ApproveTransactionDto
     * @throws AlreadyApprovedTransactionException
     * @throws AlreadyCancelledTransactionException
     * @throws DeletedPaymentMethodException
     * @throws NotFoundTransactionException
     * @throws TransactionApprovalException
     * @throws UnauthorizedPartnerException
     * @throws UnderMinimumPaymentAmountException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function approveTransaction(
        ApiSecret $partner_api_secret,
        string $transaction_id,
        string $buyer_id,
        string $buyer_name,
        string $buyer_email
    ): ApproveTransactionDto {
        PartnerAppService::validatePartner($partner_api_secret->getApiKey(), $partner_api_secret->getSecretKey());

        $transaction = self::getTransaction($transaction_id);
        if ($transaction->isApproved()) {
            throw new AlreadyApprovedTransactionException();
        }
        if ($transaction->isCanceled()) {
            throw new AlreadyCancelledTransactionException();
        }

        $pg = PgAppService::getPgById($transaction->getPgId());
        $pg_handler = Kernel::isDev() ? PgHandlerFactory::createWithTest($pg->name) : PgHandlerFactory::create($pg->name);
        $pg_bill_key = PaymentMethodAppService::getOneTimePaymentPgBillKey($transaction->getPaymentMethodId());

        $buyer = new Buyer($buyer_id, $buyer_name, $buyer_email);
        $response = $pg_handler->approveTransaction($transaction, $pg_bill_key, $buyer);
        if (!$response->isSuccess()) {
            $transaction_history = TransactionHistoryEntity::createApproveHistory(
                $transaction,
                false,
                $response->getResponseCode(),
                $response->getResponseMessage()
            );
            TransactionHistoryRepository::getRepository()->save($transaction_history);

            throw new TransactionApprovalException($response->getResponseMessage());
        }

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $transaction->approve($response->getPgTransactionId());
            TransactionRepository::getRepository()->save($transaction);

            $transaction_history = TransactionHistoryEntity::createApproveHistory(
                $transaction,
                true,
                $response->getResponseCode(),
                $response->getResponseMessage()
            );
            TransactionHistoryRepository::getRepository()->save($transaction_history);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            // 결제 승인 건 취소 처리
            $cancel_reason = '[단건 결제] PG사 결제 성공 후, 내부 승인 처리 중 오류 발생';
            $cancel_transaction_response = $pg_handler->cancelTransaction($transaction_id, $cancel_reason);
            if (!$cancel_transaction_response->isSuccess()) {
                $message = '[단건 결제] PG사 결제 성공 후, 내부 승인 처리 중 오류 발생으로 인한 PG사 결제 취소 중 오류 발생';

                $data = [
                    'extra' => [
                        'partner_transaction_id' => $transaction->getPartnerTransactionId(),
                        'transaction_id' => $transaction->getId(),
                        'pg_transaction_id' => $transaction->getPgTransactionId(),
                        'pg_id' => $transaction->getPgId(),
                        'pg_response_code' => $cancel_transaction_response->getResponseCode(),
                        'pg_response_message' => $cancel_transaction_response->getResponseMessage()
                    ]
                ];
                SentryHelper::captureMessage($message, [], $data, true);

                throw new TransactionApprovalException($cancel_transaction_response->getResponseMessage());
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
     * @param int $subscription_id
     * @param string $product_name
     * @param int $amount
     * @param \DateTime $subscribed_at
     * @param string $buyer_id
     * @param string $buyer_name
     * @param string $buyer_email
     * @return ApproveTransactionDto
     * @throws DeletedPaymentMethodException
     * @throws TransactionApprovalException
     * @throws UnderMinimumPaymentAmountException
     * @throws UnregisteredPaymentMethodException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function approveTransactionBySubscription(
        int $u_idx,
        int $payment_method_id,
        int $partner_id,
        string $partner_transaction_id,
        int $subscription_id,
        string $product_name,
        int $amount,
        \DateTime $subscribed_at,
        string $buyer_id,
        string $buyer_name,
        string $buyer_email
    ): ApproveTransactionDto {
        $pg = PgAppService::getActivePg();
        $pg_handler = Kernel::isDev() ? PgHandlerFactory::createWithTest($pg->name) : PgHandlerFactory::create($pg->name);

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
        TransactionRepository::getRepository()->save($transaction);

        $pg_bill_key = PaymentMethodAppService::getBillingPaymentPgBillKey($transaction->getPaymentMethodId());
        $buyer = new Buyer($buyer_id, $buyer_name, $buyer_email);
        $response = $pg_handler->approveTransaction($transaction, $pg_bill_key, $buyer);
        if (!$response->isSuccess()) {
            $transaction_history = TransactionHistoryEntity::createApproveHistory(
                $transaction,
                false,
                $response->getResponseCode(),
                $response->getResponseMessage()
            );
            TransactionHistoryRepository::getRepository()->save($transaction_history);

            throw new TransactionApprovalException($response->getResponseMessage());
        }

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

            // 결제 승인 건 취소 처리
            $cancel_reason = '[정기 결제] PG사 결제 성공 후, 내부 승인 처리 중 오류 발생';
            $cancel_transaction_response = $pg_handler->cancelTransaction(
                $transaction->getPgTransactionId(),
                $cancel_reason
            );
            if (!$cancel_transaction_response->isSuccess()) {
                $message = '[정기 결제] PG사 결제 성공 후, 내부 승인 처리 중 오류 발생으로 인한 PG사 결제 취소 중 오류 발생';

                $data = [
                    'extra' => [
                        'partner_transaction_id' => $transaction->getPartnerTransactionId(),
                        'transaction_id' => $transaction->getId(),
                        'pg_transaction_id' => $transaction->getPgTransactionId(),
                        'subscription_id' => $subscription_id,
                        'pg_response_code' => $cancel_transaction_response->getResponseCode(),
                        'pg_response_message' => $cancel_transaction_response->getResponseMessage()
                    ]
                ];
                SentryHelper::captureMessage($message, [], $data, true);

                throw new TransactionApprovalException($cancel_transaction_response->getResponseMessage());
            }

            throw $t;
        }

        return new ApproveTransactionDto($transaction);
    }

    /**
     * @param ApiSecret $partner_api_secret
     * @param string $transaction_id
     * @return CancelTransactionDto
     * @throws AlreadyCancelledTransactionException
     * @throws NotFoundTransactionException
     * @throws TransactionCancellationException
     * @throws UnauthorizedPartnerException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function cancelTransaction(
        ApiSecret $partner_api_secret,
        string $transaction_id
    ): CancelTransactionDto {
        PartnerAppService::validatePartner($partner_api_secret->getApiKey(), $partner_api_secret->getSecretKey());

        $transaction = self::getTransaction($transaction_id);
        if ($transaction->isCanceled()) {
            throw new AlreadyCancelledTransactionException();
        }

        $pg = PgAppService::getPgById($transaction->getPgId());
        $pg_handler = Kernel::isDev() ? PgHandlerFactory::createWithTest($pg->name) : PgHandlerFactory::create($pg->name);
        $cancel_reason = '고객 결제 취소';
        $response = $pg_handler->cancelTransaction($transaction->getPgTransactionId(), $cancel_reason);
        if (!$response->isSuccess()) {
            $transaction_history = TransactionHistoryEntity::createCancelHistory(
                $transaction,
                false,
                $response->getResponseCode(),
                $response->getResponseMessage()
            );
            TransactionHistoryRepository::getRepository()->save($transaction_history);

            throw new TransactionCancellationException($response->getResponseMessage());
        }

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $transaction->cancel();
            TransactionRepository::getRepository()->save($transaction);

            $transaction_history = TransactionHistoryEntity::createCancelHistory(
                $transaction,
                true,
                $response->getResponseCode(),
                $response->getResponseMessage()
            );
            TransactionHistoryRepository::getRepository()->save($transaction_history);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            $data = [
                'extra' => [
                    'partner_transaction_id' => $transaction->getPartnerTransactionId(),
                    'transaction_id' => $transaction->getId(),
                    'pg_transaction_id' => $transaction->getPgTransactionId()
                ]
            ];
            SentryHelper::captureMessage('PG사 결제 취소 성공 후, 내부 취소 처리 중 오류 발생', [], $data, true);

            throw $t;
        }

        return new CancelTransactionDto($transaction);
    }

    /**
     * @param ApiSecret $partner_api_secret
     * @param string $transaction_id
     * @return TransactionStatusDto
     * @throws NotFoundTransactionException
     * @throws UnauthorizedPartnerException
     * @throws UnregisteredPaymentMethodException
     * @throws UnsupportedPaymentMethodException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getTransactionStatus(
        ApiSecret $partner_api_secret,
        string $transaction_id
    ): TransactionStatusDto {
        PartnerAppService::validatePartner($partner_api_secret->getApiKey(), $partner_api_secret->getSecretKey());

        return new TransactionStatusDto(self::getTransaction($transaction_id));
    }

    /**
     * @param string $reservation_id
     * @param int $u_idx
     * @return array
     * @throws DeletedPaymentMethodException
     * @throws NotReservedTransactionException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private static function getReservedTransaction(string $reservation_id, int $u_idx): array
    {
        $reservation_key = self::getReservationKey($reservation_id);

        $redis = self::getRedisClient();
        $reserved_transaction = $redis->hgetall($reservation_key);
        if (empty($reserved_transaction)
            || (PaymentMethodAppService::getUidxById(intval($reserved_transaction['payment_method_id'])) !== $u_idx)
        ) {
            throw new NotReservedTransactionException();
        }

        return $reserved_transaction;
    }

    /**
     * @param string $reservation_id
     * @return string
     */
    public static function getReservationKey(string $reservation_id): string
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
     * @throws NotFoundTransactionException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private static function getTransaction(string $transaction_id): TransactionEntity
    {
        $transaction = TransactionRepository::getRepository()->findOneByUuid(Uuid::fromString($transaction_id));
        if (is_null($transaction)) {
            throw new NotFoundTransactionException();
        }

        return $transaction;
    }
}
