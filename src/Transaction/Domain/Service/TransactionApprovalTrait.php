<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Service;

use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\SentryHelper;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Pg\Domain\Service\Buyer;
use RidiPay\Pg\Domain\Service\PgHandlerInterface;
use RidiPay\Pg\Domain\Service\TransactionApprovalResponse;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;
use RidiPay\Transaction\Domain\Repository\TransactionHistoryRepository;
use RidiPay\Transaction\Domain\Repository\TransactionRepository;

trait TransactionApprovalTrait
{
    /**
     * @param TransactionEntity $transaction
     * @param PgHandlerInterface $pg_handler
     * @param string $pg_bill_key
     * @param Buyer $buyer
     * @return TransactionEntity
     * @throws TransactionApprovalException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function approveTransaction(
        TransactionEntity $transaction,
        PgHandlerInterface $pg_handler,
        string $pg_bill_key,
        Buyer $buyer
    ): TransactionEntity {
        $pg_response = $pg_handler->approveTransaction($transaction, $pg_bill_key, $buyer);
        if (!$pg_response->isSuccess()) {
            self::createTransactionHistory($transaction, $pg_response);

            throw new TransactionApprovalException($pg_response->getResponseMessage());
        }

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $transaction->approve($pg_response->getPgTransactionId());
            TransactionRepository::getRepository()->save($transaction);

            self::createTransactionHistory($transaction, $pg_response);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            self::refundPgTransaction($transaction, $pg_handler);

            throw $t;
        }

        return $transaction;
    }

    /**
     * @param TransactionEntity $transaction
     * @param TransactionApprovalResponse $pg_response
     * @return TransactionHistoryEntity
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private static function createTransactionHistory(
        TransactionEntity $transaction,
        TransactionApprovalResponse $pg_response
    ): TransactionHistoryEntity {
        $transaction_history = TransactionHistoryEntity::createApproveHistory(
            $transaction,
            $pg_response->isSuccess(),
            $pg_response->getResponseCode(),
            $pg_response->getResponseMessage()
        );
        TransactionHistoryRepository::getRepository()->save($transaction_history);

        return $transaction_history;
    }

    /**
     * 결제 승인 건 환불 처리
     *
     * @param TransactionEntity $transaction
     * @param PgHandlerInterface $pg_handler
     * @throws TransactionApprovalException
     */
    private static function refundPgTransaction(
        TransactionEntity $transaction,
        PgHandlerInterface $pg_handler
    ): void {
        $cancel_reason = 'PG사 결제 성공 후, 내부 승인 처리 중 오류 발생';
        $cancel_transaction_response = $pg_handler->cancelTransaction(
            $transaction->getPgTransactionId(),
            $cancel_reason
        );
        if (!$cancel_transaction_response->isSuccess()) {
            $message = 'PG사 결제 성공 후, 내부 승인 처리 중 오류 발생으로 인한 PG사 결제 취소 중 오류 발생';

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
    }
}