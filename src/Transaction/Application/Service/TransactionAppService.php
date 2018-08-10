<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Service;

use Predis\Client;
use Ramsey\Uuid\Uuid;
use Ridibooks\Library\TimeConstant;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Pg\Application\Service\PgAppService;
use RidiPay\Transaction\Application\Dto\ApproveTransactionDto;
use RidiPay\Transaction\Application\Dto\CancelTransactionDto;
use RidiPay\Transaction\Application\Dto\TransactionStatusDto;
use RidiPay\Transaction\Application\Exception\NonTransactionOwnerException;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;
use RidiPay\Transaction\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Transaction\Domain\Repository\TransactionHistoryRepository;
use RidiPay\Transaction\Domain\Repository\TransactionRepository;
use RidiPay\Pg\Domain\Service\PgHandlerFactory;
use RidiPay\Transaction\Application\Dto\CreateTransactionDto;
use RidiPay\User\Application\Service\PaymentMethodAppService;

class TransactionAppService
{
    /**
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param int $u_idx
     * @param string $payment_method_id
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     * @param string $return_url
     * @return string
     * @throws UnauthorizedPartnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function reserveTransaction(
        string $partner_api_key,
        string $partner_secret_key,
        int $u_idx,
        string $payment_method_id,
        string $partner_transaction_id,
        string $product_name,
        int $amount,
        string $return_url
    ): string {
        PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);

        $reservation_id = Uuid::uuid4()->toString();
        $reservation_key = self::getReservationKey($reservation_id);

        $redis = self::getRedisClient();
        $redis->hmset(
            $reservation_key,
            [
                'u_idx' => $u_idx,
                'payment_method_id' => $payment_method_id,
                'partner_api_key' => $partner_api_key,
                'partner_transaction_id' => $partner_transaction_id,
                'product_name' => $product_name,
                'amount' => $amount,
                'return_url' => $return_url,
                'reserved_at' => (new \DateTime())->format(DATE_ATOM)
            ]
        );
        $redis->expire($reservation_key, TimeConstant::SEC_IN_HOUR);

        return $reservation_id;
    }

    /**
     * @param int $u_idx
     * @param string $reservation_id
     * @return CreateTransactionDto
     * @throws NonTransactionOwnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function createTransaction(int $u_idx, string $reservation_id): CreateTransactionDto
    {
        $reserved_transaction = self::getReservedTransaction($u_idx, $reservation_id);

        $payment_method_id = PaymentMethodAppService::getPaymentMethodIdByUuid($reserved_transaction['payment_method_id']);
        $pg = PgAppService::getActivePg();
        $partner_id = PartnerAppService::getPartnerIdByApiKey($reserved_transaction['partner_api_key']);
        $partner_transaction_id = $reserved_transaction['partner_transaction_id'];

        $transaction = new TransactionEntity(
            $u_idx,
            $payment_method_id,
            $pg->id,
            $partner_id,
            $partner_transaction_id,
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
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param int $u_idx
     * @param string $transaction_id
     * @return ApproveTransactionDto
     * @throws NonTransactionOwnerException
     * @throws UnauthorizedPartnerException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function approveTransaction(
        string $partner_api_key,
        string $partner_secret_key,
        int $u_idx,
        string $transaction_id
    ): ApproveTransactionDto {
        PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);

        $transaction = self::getTransaction($u_idx, $transaction_id);

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

            // RIDI Pay 승인 처리 중 오류 발생 시, 결제 승인 건 취소 처리
            $cancel_reason = '';
            $pg_handler->cancelTransaction($transaction_id, $cancel_reason);

            throw $t;
        }

        return new ApproveTransactionDto($transaction);
    }

    /**
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param int $u_idx
     * @param string $transaction_id
     * @return CancelTransactionDto
     * @throws NonTransactionOwnerException
     * @throws UnauthorizedPartnerException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function cancelTransaction(
        string $partner_api_key,
        string $partner_secret_key,
        int $u_idx,
        string $transaction_id
    ): CancelTransactionDto {
        PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);

        $transaction = self::getTransaction($u_idx, $transaction_id);

        $pg = PgAppService::getPgById($transaction->getPgId());
        $pg_handler = PgHandlerFactory::create($pg->name);
        $cancel_reason = '';
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

            throw $t;
        }

        return new CancelTransactionDto($transaction);
    }

    /**
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param int $u_idx
     * @param string $transaction_id
     * @return TransactionStatusDto
     * @throws NonTransactionOwnerException
     * @throws UnauthorizedPartnerException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getTransactionStatus(
        string $partner_api_key,
        string $partner_secret_key,
        int $u_idx,
        string $transaction_id
    ): TransactionStatusDto {
        PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);

        return new TransactionStatusDto(self::getTransaction($u_idx, $transaction_id));
    }

    /**
     * @param int $u_idx
     * @param string $reservation_id
     * @return array
     * @throws NonTransactionOwnerException
     */
    private static function getReservedTransaction(int $u_idx, string $reservation_id): array
    {
        $reservation_key = self::getReservationKey($reservation_id);

        $redis = self::getRedisClient();
        $reserved_transaction = $redis->hgetall($reservation_key);
        if ($u_idx !== intval($reserved_transaction['u_idx'])) {
            throw new NonTransactionOwnerException();
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
     * @param int $u_idx
     * @param string $transaction_id
     * @return TransactionEntity
     * @throws NonTransactionOwnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private static function getTransaction(int $u_idx, string $transaction_id): TransactionEntity
    {
        $transaction = TransactionRepository::getRepository()->findOneByUuid(Uuid::fromString($transaction_id));
        if ($u_idx !== $transaction->getUidx()) {
            throw new NonTransactionOwnerException();
        }

        return $transaction;
    }
}