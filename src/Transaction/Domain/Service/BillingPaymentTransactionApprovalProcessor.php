<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Kernel;
use RidiPay\Transaction\Domain\Service\IdempotentTransactionApprovalProcessor;
use RidiPay\Pg\Application\Dto\PgDto;
use RidiPay\Pg\Application\Service\PgAppService;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\Service\Buyer;
use RidiPay\Pg\Domain\Service\PgHandlerFactory;
use RidiPay\Pg\Domain\Service\PgHandlerInterface;
use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Exception\NotFoundSubscriptionException;
use RidiPay\Transaction\Domain\Exception\NotFoundTransactionException;
use RidiPay\Transaction\Domain\Repository\SubscriptionRepository;
use RidiPay\Transaction\Domain\Repository\TransactionRepository;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Domain\Exception\DeletedPaymentMethodException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;

class BillingPaymentTransactionApprovalProcessor extends IdempotentTransactionApprovalProcessor
{
    use TransactionApprovalTrait;

    /** @var SubscriptionEntity */
    private $subscription;

    /** @var PgDto */
    private $pg;

    /** @var PgHandlerInterface */
    private $pg_handler;

    /** @var int */
    private $u_idx;

    /** @var int */
    private $partner_id;

    /** @var string */
    private $partner_transaction_id;

    /** @var int */
    private $amount;

    /** @var Buyer */
    private $buyer;

    /**
     * @param string $subscription_uuid
     * @param int $partner_id
     * @param string $partner_transaction_id
     * @param int $amount
     * @param Buyer $buyer
     * @param string $invoice_id
     * @throws DeletedPaymentMethodException
     * @throws NotFoundSubscriptionException
     * @throws UnregisteredPaymentMethodException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function __construct(
        string $subscription_uuid,
        int $partner_id,
        string $partner_transaction_id,
        int $amount,
        Buyer $buyer,
        string $invoice_id
    ) {
        $this->subscription = self::getSubscription($subscription_uuid);
        $this->pg = PgAppService::getActivePg();
        $this->pg_handler = $pg_handler = Kernel::isDev()
            ? PgHandlerFactory::createWithTest($this->pg->name)
            : PgHandlerFactory::create($this->pg->name);
        $this->u_idx = PaymentMethodAppService::getUidxById($this->subscription->getPaymentMethodId());
        $this->partner_id = $partner_id;
        $this->partner_transaction_id = $partner_transaction_id;
        $this->amount = $amount;
        $this->buyer = $buyer;

        // 서로 다른 subscription 간 동일한 invoice id가 입력될 수 있기 때문에 invoice id와 subscription uuid를 병행 이용
        parent::__construct("BILLING_PAYMENT_TRANSACTION_APPROVAL:{$subscription_uuid}:{$invoice_id}");
    }

    /**
     * @return BillingPaymentTransactionApprovalResult
     * @throws DeletedPaymentMethodException
     * @throws TransactionApprovalException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    protected function run(): TransactionApprovalResult
    {
        $transaction = $this->createTransaction();
        $pg_bill_key = PaymentMethodAppService::getBillingPaymentPgBillKey($transaction->getPaymentMethodId());
        $transaction = self::approveTransaction($transaction, $this->pg_handler, $pg_bill_key, $this->buyer);

        return new BillingPaymentTransactionApprovalResult($this->subscription, $transaction);
    }

    /**
     * @return BillingPaymentTransactionApprovalResult
     * @throws NotFoundTransactionException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    protected function getResult(): TransactionApprovalResult
    {
        $content = json_decode($this->getSerializedResult());

        $transaction = TransactionRepository::getRepository()->findOneByUuid(Uuid::fromString($content->transaction_id));
        if ($transaction === null) {
            throw new NotFoundTransactionException();
        }

        return new BillingPaymentTransactionApprovalResult($this->subscription, $transaction);
    }

    /**
     * @param string $subscription_uuid
     * @return SubscriptionEntity
     * @throws NotFoundSubscriptionException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private static function getSubscription(string $subscription_uuid): SubscriptionEntity
    {
        $subscription = SubscriptionRepository::getRepository()->findOneByUuid(Uuid::fromString($subscription_uuid));
        if (is_null($subscription)) {
            throw new NotFoundSubscriptionException();
        }
        self::assertPayableSubscription($subscription);

        return $subscription;
    }

    /**
     * @param SubscriptionEntity $subscription
     * @throws NotFoundSubscriptionException
     */
    private static function assertPayableSubscription(SubscriptionEntity $subscription): void
    {
        if ($subscription->isUnsubscribed()) {
            throw new NotFoundSubscriptionException();
        }
    }

    /**
     * @return TransactionEntity
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private function createTransaction(): TransactionEntity
    {
        $transaction = new TransactionEntity(
            $this->u_idx,
            $this->subscription->getPaymentMethodId(),
            $this->pg->id,
            $this->partner_id,
            $this->partner_transaction_id,
            $this->subscription->getProductName(),
            $this->amount,
            $this->subscription->getSubscribedAt()
        );
        TransactionRepository::getRepository()->save($transaction);

        return $transaction;
    }
}
