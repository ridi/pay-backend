<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

use RidiPay\Kernel;
use RidiPay\Pg\Application\Service\PgAppService;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\Service\PgHandlerFactory;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;

class TransactionStatusDto
{
    /** @var string */
    public $transaction_id;

    /** @var string */
    public $partner_transaction_id;

    /** @var string */
    public $payment_method_id;

    /** @var string */
    public $payment_method_type;

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
     * @throws UnregisteredPaymentMethodException
     * @throws UnsupportedPaymentMethodException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function __construct(TransactionEntity $transaction)
    {
        $this->transaction_id = $transaction->getUuid()->toString();
        $this->partner_transaction_id = $transaction->getPartnerTransactionId();

        $payment_method_id = $transaction->getPaymentMethodId();
        $payment_method = PaymentMethodAppService::getPaymentMethod($payment_method_id);
        $this->payment_method_id = $payment_method->payment_method_id;
        $this->payment_method_type = $payment_method->getType();

        $this->status = $transaction->getStatus();
        $this->product_name = $transaction->getProductName();
        $this->amount = $transaction->getAmount();
        $this->reserved_at = $transaction->getReservedAt();
        $this->approved_at = $transaction->getApprovedAt();
        $this->canceled_at = $transaction->getCanceledAt();

        if ($payment_method->isCard() && !$transaction->isReserved()) {
            $pg = PgAppService::getPgById($transaction->getPgId());
            $pg_handler = Kernel::isDev() ? PgHandlerFactory::createWithTest($pg->name) : PgHandlerFactory::create($pg->name);
            $this->card_receipt_url = $pg_handler->getCardReceiptUrl($transaction);
        }
    }
}
