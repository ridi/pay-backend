<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Dto;

use RidiPay\Transaction\Entity\TransactionEntity;
use RidiPay\Transaction\Exception\UnsupportedPgException;
use RidiPay\Transaction\Repository\PgRepository;
use RidiPay\Transaction\Service\Pg\PgHandlerFactory;
use RidiPay\User\Service\PaymentMethodService;

class TransactionStatusDto implements \JsonSerializable
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
        $this->approved_at = $transaction->getApprovedAt();
        $this->canceled_at = $transaction->getCanceledAt();

        if (PaymentMethodService::isCard($transaction->getPaymentMethodId()) && $transaction->isApproved()) {
            $pg = PgRepository::getRepository()->findOneById($transaction->getPgId());
            $pg_handler = PgHandlerFactory::create($pg->getName());
            $this->card_receipt_url = $pg_handler->getCardReceiptUrl($transaction);
        }
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        $data = [
            'transaction_id' => $this->transaction_id,
            'partner_transaction_id' => $this->partner_transaction_id,
            'status' => $this->status,
            'product_name' => $this->product_name,
            'amount' => $this->amount
        ];

        if (!is_null($this->approved_at)) {
            $data['approved_at'] = $this->approved_at;
        }
        if (!is_null($this->canceled_at)) {
            $data['canceled_at'] = $this->canceled_at;
        }
        if (!is_null($this->card_receipt_url)) {
            $data['card_receipt_url'] = $this->card_receipt_url;
        }
    }
}
