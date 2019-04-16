<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Kernel;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\IdempotentRequestProcessor;
use RidiPay\Library\SentryHelper;
use RidiPay\Pg\Application\Dto\PgDto;
use RidiPay\Pg\Application\Service\PgAppService;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\Service\Buyer;
use RidiPay\Pg\Domain\Service\PgHandlerFactory;
use RidiPay\Pg\Domain\Service\PgHandlerInterface;
use RidiPay\Pg\Domain\Service\TransactionApprovalResponse;
use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;
use RidiPay\Transaction\Domain\Exception\NotFoundSubscriptionException;
use RidiPay\Transaction\Domain\Repository\SubscriptionRepository;
use RidiPay\Transaction\Domain\Repository\TransactionHistoryRepository;
use RidiPay\Transaction\Domain\Repository\TransactionRepository;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Domain\Exception\DeletedPaymentMethodException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;

class BillingPaymentTransactionApprovalProcessor extends IdempotentRequestProcessor
{
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
        $this->pg_handler = $pg_handler = Kernel::isLocal()
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
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    protected function run(): BillingPaymentTransactionApprovalResult
    {
        $transaction = $this->createTransaction();

        $pg_bill_key = PaymentMethodAppService::getBillingPaymentPgBillKey($transaction->getPaymentMethodId());
        $pg_response = $this->pg_handler->approveTransaction($transaction, $pg_bill_key, $this->buyer);
        if (!$pg_response->isSuccess()) {
            $this->createTransactionHistory($transaction, $pg_response);

            throw new TransactionApprovalException($pg_response->getResponseMessage());
        }

        $transaction = $this->makeTransactionApproved($transaction, $pg_response);

        return new BillingPaymentTransactionApprovalResult(
            $this->subscription->getUuid()->toString(),
            $transaction->getUuid()->toString(),
            $transaction->getPartnerTransactionId(),
            $transaction->getProductName(),
            $transaction->getAmount(),
            $this->subscription->getSubscribedAt(),
            $transaction->getApprovedAt()
        );
    }

    /**
     * @return BillingPaymentTransactionApprovalResult
     */
    protected function getResult(): BillingPaymentTransactionApprovalResult
    {
        $content = json_decode($this->getSerializedResult());

        return new BillingPaymentTransactionApprovalResult(
            $content->subscription_id,
            $content->transaction_id,
            $content->partner_transaction_id,
            $content->product_name,
            (int) $content->amount,
            \DateTime::createFromFormat(DATE_ATOM, $content->subscribed_at),
            \DateTime::createFromFormat(DATE_ATOM, $content->approved_at)
        );
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

    /**
     * @param TransactionEntity $transaction
     * @param TransactionApprovalResponse $pg_response
     * @return TransactionHistoryEntity
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private function createTransactionHistory(
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
     * @param TransactionEntity $transaction
     * @param TransactionApprovalResponse $pg_response
     * @return TransactionEntity
     * @throws TransactionApprovalException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    private function makeTransactionApproved(
        TransactionEntity $transaction,
        TransactionApprovalResponse $pg_response
    ): TransactionEntity {
        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $transaction->approve($pg_response->getPgTransactionId());
            TransactionRepository::getRepository()->save($transaction);

            $this->createTransactionHistory($transaction, $pg_response);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            $this->refundPgTransaction($transaction);

            throw $t;
        }

        return $transaction;
    }

    /**
     * 결제 승인 건 환불 처리
     *
     * @param TransactionEntity $transaction
     * @throws TransactionApprovalException
     */
    private function refundPgTransaction(TransactionEntity $transaction): void
    {
        $cancel_reason = '[정기 결제] PG사 결제 성공 후, 내부 승인 처리 중 오류 발생';
        $cancel_transaction_response = $this->pg_handler->cancelTransaction(
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
                    'subscription_id' => $this->subscription->getId(),
                    'pg_response_code' => $cancel_transaction_response->getResponseCode(),
                    'pg_response_message' => $cancel_transaction_response->getResponseMessage()
                ]
            ];
            SentryHelper::captureMessage($message, [], $data, true);

            throw new TransactionApprovalException($cancel_transaction_response->getResponseMessage());
        }
    }
}
