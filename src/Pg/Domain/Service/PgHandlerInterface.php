<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Service;

use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Pg\Domain\Exception\TransactionCancellationException;
use RidiPay\Pg\Domain\Exception\CardRegistrationException;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;

interface PgHandlerInterface
{
    /**
     * @param string $card_number
     * @param string $card_expiration_date
     * @param string $card_password
     * @param string $tax_id
     * @return CardRegistrationResponse
     * @throws CardRegistrationException
     */
    public function registerCard(
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id
    ): CardRegistrationResponse;

    /**
     * @param TransactionEntity $transaction
     * @return TransactionApprovalResponse
     * @throws TransactionApprovalException
     */
    public function approveTransaction(TransactionEntity $transaction): TransactionApprovalResponse;

    /**
     * @param string $pg_transaction_id
     * @param string $cancel_reason
     * @return TransactionCancellationResponse
     * @throws TransactionCancellationException
     */
    public function cancelTransaction(string $pg_transaction_id, string $cancel_reason): TransactionCancellationResponse;

    /**
     * @param TransactionEntity $transaction
     * @return string
     */
    public function getCardReceiptUrl(TransactionEntity $transaction): string;
}
