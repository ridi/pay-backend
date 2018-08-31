<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Service;

use Predis\Client as PredisClient;
use Predis\Client;
use Ramsey\Uuid\Uuid;
use Ridibooks\Library\TimeConstant;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Transaction\Dto\ApproveTransactionDto;
use RidiPay\Transaction\Dto\CancelTransactionDto;
use RidiPay\Transaction\Dto\TransactionStatusDto;
use RidiPay\Transaction\Entity\PartnerEntity;
use RidiPay\Transaction\Entity\TransactionEntity;
use RidiPay\Transaction\Entity\TransactionHistoryEntity;
use RidiPay\Transaction\Exception\UnauthorizedPartnerException;
use RidiPay\Transaction\Exception\UnsupportedPgException;
use RidiPay\Transaction\Repository\PartnerRepository;
use RidiPay\Transaction\Repository\PgRepository;
use RidiPay\Transaction\Repository\TransactionHistoryRepository;
use RidiPay\Transaction\Repository\TransactionRepository;
use RidiPay\Transaction\Service\Pg\PgHandlerFactory;
use RidiPay\Transaction\Dto\CreateTransactionDto;
use RidiPay\User\Service\PaymentMethodService;

class TransactionService
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
     * @throws \Exception
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
        $partner = self::getPartner($partner_api_key, $partner_secret_key);

        $redis = self::getRedisClient();
        $reservation_id = Uuid::uuid4()->toString();
        $reservation_key = self::getReservationKey($reservation_id);
        $redis->hmset(
            $reservation_key,
            [
                'u_idx' => $u_idx,
                'payment_method_id' => $payment_method_id,
                'partner_id' => $partner->getId(),
                'partner_transaction_id' => $partner_transaction_id,
                'product_name' => $product_name,
                'amount' => $amount,
                'return_url' => $return_url
            ]
        );
        $redis->expire($reservation_key, TimeConstant::SEC_IN_HOUR);

        return $reservation_id;
    }

    /**
     * @param string $reservation_id
     * @return CreateTransactionDto
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function createTransaction(string $reservation_id): CreateTransactionDto
    {
        $redis = self::getRedisClient();
        $reservation_key = self::getReservationKey($reservation_id);
        $transaction_data = $redis->hgetall($reservation_key);

        $payment_method_id = PaymentMethodService::getPaymentMethodIdByUuid($transaction_data['payment_method_id']);
        $pg = PgRepository::getRepository()->findActiveOne();
        $partner_transaction_id = $transaction_data['partner_transaction_id'];

        $transaction = new TransactionEntity(
            intval($transaction_data['u_idx']),
            $payment_method_id,
            $pg->getId(),
            intval($transaction_data['partner_id']),
            $partner_transaction_id,
            $transaction_data['product_name'],
            intval($transaction_data['amount'])
        );
        TransactionRepository::getRepository()->save($transaction);

        return new CreateTransactionDto(
            $transaction->getUuid()->toString(),
            $partner_transaction_id,
            $transaction_data['return_url']
        );
    }

    /**
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param int $u_idx
     * @param string $transaction_id
     * @param bool $is_test
     * @return ApproveTransactionDto
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function approveTransaction(
        string $partner_api_key,
        string $partner_secret_key,
        int $u_idx,
        string $transaction_id,
        bool $is_test = false
    ) {
        $partner = self::getPartner($partner_api_key, $partner_secret_key);

        $transaction = TransactionRepository::getRepository()->findOneByUuid(Uuid::fromString($transaction_id));
        if ($u_idx !== $transaction->getUidx()) {
            throw new \Exception();
        }

        $pg = PgRepository::getRepository()->findOneById($transaction->getPgId());
        $pg_handler = PgHandlerFactory::create($pg->getName(), $is_test);
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
     * @param bool $is_test
     * @return CancelTransactionDto
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function cancelTransaction(
        string $partner_api_key,
        string $partner_secret_key,
        int $u_idx,
        string $transaction_id,
        bool $is_test = false
    ) {
        $partner = self::getPartner($partner_api_key, $partner_secret_key);

        $transaction = TransactionRepository::getRepository()->findOneByUuid(Uuid::fromString($transaction_id));
        if ($u_idx !== $transaction->getUidx()) {
            throw new \Exception();
        }

        $pg = PgRepository::getRepository()->findOneById($transaction->getPgId());
        $pg_handler = PgHandlerFactory::create($pg->getName(), $is_test);
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
     * @throws UnauthorizedPartnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getTransactionStatus(
        string $partner_api_key,
        string $partner_secret_key,
        int $u_idx,
        string $transaction_id
    ): TransactionStatusDto {
        $partner = self::getPartner($partner_api_key, $partner_secret_key);

        $transaction = TransactionRepository::getRepository()->findOneByUuid(Uuid::fromString($transaction_id));
        if ($u_idx !== $transaction->getUidx()) {
            throw new \Exception();
        }

        return new TransactionStatusDto($transaction);
    }

    /**
     * @return Client
     */
    private static function getRedisClient(): Client
    {
        return new PredisClient(['host' => getenv('REDIS_HOST')]);
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
     * @param string $api_key
     * @param string $secret_key
     * @return PartnerEntity
     * @throws UnauthorizedPartnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private static function getPartner(string $api_key, string $secret_key): PartnerEntity
    {
        $partner = PartnerRepository::getRepository()->findOneByApiKey($api_key);
        if (is_null($partner) || !$partner->isValidSecretKey($secret_key)) {
            throw new UnauthorizedPartnerException();
        }

        return $partner;
    }
}
