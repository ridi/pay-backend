<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Service;

use RidiPay\Pg\Domain\Exception\PgException;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;

interface PgHandlerInterface
{
    /**
     * @param string $card_number
     * @param string $card_expiration_date
     * @param string $card_password
     * @param string $tax_id
     * @return RegisterCardResponse
     * @throws PgException
     */
    public function registerCard(
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id
    ): RegisterCardResponse;

    /**
     * @param TransactionEntity $transaction
     * @return ApproveTransactionResponse
     * @throws PgException
     */
    public function approveTransaction(TransactionEntity $transaction): ApproveTransactionResponse;

    /**
     * @param string $pg_transaction_id
     * @param string $cancel_reason
     * @return CancelTransactionResponse
     * @throws PgException
     */
    public function cancelTransaction(string $pg_transaction_id, string $cancel_reason): CancelTransactionResponse;

    /**
     * @param TransactionEntity $transaction
     * @return string
     */
    public function getCardReceiptUrl(TransactionEntity $transaction): string;
}
