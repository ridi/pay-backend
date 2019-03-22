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
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;
use RidiPay\Transaction\Domain\Repository\TransactionHistoryRepository;
use RidiPay\Transaction\Domain\Repository\TransactionRepository;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Domain\Exception\DeletedPaymentMethodException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;

class OneTimePaymentTransactionApprovalProcessor extends IdempotentRequestProcessor
{
    private const REQUEST_TYPE = 'ONE_TIME_PAYMENT_TRANSACTION_APPROVAL';

    /** @var TransactionEntity */
    private $transaction;

    /** @var Buyer */
    private $buyer;

    /**
     * @param TransactionEntity $transaction
     * @param Buyer $buyer
     */
    public function __construct(TransactionEntity $transaction, Buyer $buyer)
    {
        parent::__construct(
            self::REQUEST_TYPE,
            ['transaction_id' => $transaction->getUuid()->toString()]
        );

        $this->transaction = $transaction;
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
        $pg = PgAppService::getPgById($this->transaction->getPgId());
        $pg_handler = Kernel::isLocal()
            ? PgHandlerFactory::createWithTest($pg->name)
            : PgHandlerFactory::create($pg->name);
        $pg_bill_key = PaymentMethodAppService::getOneTimePaymentPgBillKey($this->transaction->getPaymentMethodId());

        $response = $pg_handler->approveTransaction($this->transaction, $pg_bill_key, $this->buyer);
        if (!$response->isSuccess()) {
            $transaction_history = TransactionHistoryEntity::createApproveHistory(
                $this->transaction,
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
            $this->transaction->approve($response->getPgTransactionId());
            TransactionRepository::getRepository()->save($this->transaction);

            $transaction_history = TransactionHistoryEntity::createApproveHistory(
                $this->transaction,
                true,
                $response->getResponseCode(),
                $response->getResponseMessage()
            );
            TransactionHistoryRepository::getRepository()->save($transaction_history);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            // 결제 승인 건 취소 처리
            $cancel_reason = '[단건 결제] PG사 결제 성공 후, 내부 승인 처리 중 오류 발생';
            $cancel_transaction_response = $pg_handler->cancelTransaction(
                $this->transaction->getPgTransactionId(),
                $cancel_reason
            );
            if (!$cancel_transaction_response->isSuccess()) {
                $message = '[단건 결제] PG사 결제 성공 후, 내부 승인 처리 중 오류 발생으로 인한 PG사 결제 취소 중 오류 발생';

                $data = [
                    'extra' => [
                        'partner_transaction_id' => $this->transaction->getPartnerTransactionId(),
                        'transaction_id' => $this->transaction->getId(),
                        'pg_transaction_id' => $this->transaction->getPgTransactionId(),
                        'pg_id' => $this->transaction->getPgId(),
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
            $this->transaction->getUuid()->toString(),
            $this->transaction->getPartnerTransactionId(),
            $this->transaction->getProductName(),
            $this->transaction->getAmount(),
            $this->transaction->getReservedAt(),
            $this->transaction->getApprovedAt()
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
