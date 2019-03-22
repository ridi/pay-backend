<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Service;

use RidiPay\Kernel;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\IdempotentRequestProcessor;
use RidiPay\Library\SentryHelper;
use RidiPay\Pg\Application\Service\PgAppService;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\Service\Buyer;
use RidiPay\Pg\Domain\Service\PgHandlerFactory;
use RidiPay\Transaction\Application\Dto\ApproveTransactionDto;
use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;
use RidiPay\Transaction\Domain\Repository\TransactionHistoryRepository;
use RidiPay\Transaction\Domain\Repository\TransactionRepository;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Domain\Exception\DeletedPaymentMethodException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;

class BillingPaymentTransactionApprovalProcessor extends IdempotentRequestProcessor
{
    private const REQUEST_TYPE = 'BILLING_PAYMENT_TRANSACTION_APPROVAL';

    /** @var SubscriptionEntity */
    private $subscription;

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
     * @param SubscriptionEntity $subscription
     * @param int $partner_id
     * @param string $partner_transaction_id
     * @param int $amount
     * @param Buyer $buyer
     * @param string $invoice_id
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws DeletedPaymentMethodException
     * @throws UnregisteredPaymentMethodException
     */
    public function __construct(
        SubscriptionEntity $subscription,
        int $partner_id,
        string $partner_transaction_id,
        int $amount,
        Buyer $buyer,
        string $invoice_id
    ) {
        // 서로 다른 subscription 간 동일한 invoice id가 입력될 수 있기 때문에 invoice id와 subscription id를 병행 이용
        parent::__construct(
            self::REQUEST_TYPE,
            [
                'invoice_id' => $invoice_id,
                'subscription_id' => $subscription->getUuid()->toString()
            ]
        );

        $this->subscription = $subscription;
        $this->u_idx = PaymentMethodAppService::getUidxById($subscription->getPaymentMethodId());
        $this->partner_id = $partner_id;
        $this->partner_transaction_id = $partner_transaction_id;
        $this->amount = $amount;
        $this->buyer = $buyer;
    }

    /**
     * @return ApproveTransactionDto
     * @throws DeletedPaymentMethodException
     * @throws TransactionApprovalException
     * @throws UnregisteredPaymentMethodException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    protected function run(): ApproveTransactionDto
    {
        $pg = PgAppService::getActivePg();
        $pg_handler = Kernel::isLocal()
            ? PgHandlerFactory::createWithTest($pg->name)
            : PgHandlerFactory::create($pg->name);

        $transaction = new TransactionEntity(
            $this->u_idx,
            $this->subscription->getPaymentMethodId(),
            $pg->id,
            $this->partner_id,
            $this->partner_transaction_id,
            $this->subscription->getProductName(),
            $this->amount,
            $this->subscription->getSubscribedAt()
        );
        TransactionRepository::getRepository()->save($transaction);

        $pg_bill_key = PaymentMethodAppService::getBillingPaymentPgBillKey($transaction->getPaymentMethodId());
        $response = $pg_handler->approveTransaction($transaction, $pg_bill_key, $this->buyer);
        if (!$response->isSuccess()) {
            $transaction_history = TransactionHistoryEntity::createApproveHistory(
                $transaction,
                false,
                $response->getResponseCode(),
                $response->getResponseMessage()
            );
            TransactionHistoryRepository::getRepository()->save($transaction_history);

            throw new TransactionApprovalException($response->getResponseMessage());
        }

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

            // 결제 승인 건 취소 처리
            $cancel_reason = '[정기 결제] PG사 결제 성공 후, 내부 승인 처리 중 오류 발생';
            $cancel_transaction_response = $pg_handler->cancelTransaction(
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

            throw $t;
        }

        return new ApproveTransactionDto(
            $transaction->getUuid()->toString(),
            $transaction->getPartnerTransactionId(),
            $transaction->getProductName(),
            $transaction->getAmount(),
            $transaction->getReservedAt(),
            $transaction->getApprovedAt()
        );
    }

    /**
     * @return ApproveTransactionDto
     */
    protected function getResult(): ApproveTransactionDto
    {
        $content = json_decode($this->getSerializedResult());

        return new ApproveTransactionDto(
            $content->transaction_id,
            $content->partner_transaction_id,
            $content->product_name,
            (int) $content->amount,
            \DateTime::createFromFormat(DATE_ATOM, $content->reserved_at),
            \DateTime::createFromFormat(DATE_ATOM, $content->approved_at)
        );
    }
}
