<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

use RidiPay\Pg\Application\Service\PgAppService;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\Service\PgHandlerFactory;
use RidiPay\User\Application\Service\PaymentMethodAppService;

class TransactionStatusDto
{
    /** @var string */
    public $transaction_id;

    /** @var string */
    public $partner_transaction_id;

    /** @var string */
    public $status;

    /** @var string */
    public $product_name;

    /** @var int */
    public $amount;

    /** @var \DateTime */
    public $reserved_at;

    /** @var \DateTime|null */
    public $approved_at;

    /** @var \DateTime|null */
    public $canceled_at;

    /** @var string|null */
    public $card_receipt_url;

    /**
     * @param TransactionEntity $transaction
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function __construct(TransactionEntity $transaction)
    {
        $this->transaction_id = $transaction->getUuid()->toString();
        $this->partner_transaction_id = $transaction->getPartnerTransactionId();
        $this->status = $transaction->getStatus();
        $this->product_name = $transaction->getProductName();
        $this->amount = $transaction->getAmount();
        $this->reserved_at = $transaction->getReservedAt();
        $this->approved_at = $transaction->getApprovedAt();
        $this->canceled_at = $transaction->getCanceledAt();

        if (PaymentMethodAppService::isCard($transaction->getPaymentMethodId()) && $transaction->isApproved()) {
            $pg = PgAppService::getPgById($transaction->getPgId());
            $pg_handler = PgHandlerFactory::create($pg->name);
            $this->card_receipt_url = $pg_handler->getCardReceiptUrl($transaction);
        }
    }
}
