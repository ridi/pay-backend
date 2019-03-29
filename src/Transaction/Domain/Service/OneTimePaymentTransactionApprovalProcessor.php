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
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;
use RidiPay\Transaction\Domain\Exception\AlreadyCancelledTransactionException;
use RidiPay\Transaction\Domain\Exception\NotFoundTransactionException;
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

    /** @var PgDto */
    private $pg;

    /** @var PgHandlerInterface */
    private $pg_handler;

    /** @var Buyer */
    private $buyer;

    /**
     * @param string $transaction_uuid
     * @param Buyer $buyer
     * @throws AlreadyCancelledTransactionException
     * @throws NotFoundTransactionException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function __construct(string $transaction_uuid, Buyer $buyer)
    {
        $this->transaction = self::getTransaction($transaction_uuid);
        $this->pg = PgAppService::getPgById($this->transaction->getPgId());
        $this->pg_handler = Kernel::isLocal()
            ? PgHandlerFactory::createWithTest($this->pg->name)
            : PgHandlerFactory::create($this->pg->name);
        $this->buyer = $buyer;

        parent::__construct(
            self::REQUEST_TYPE,
            ['transaction_id' => $transaction_uuid]
        );
    }

    /**
     * @return OneTimePaymentTransactionApprovalResult
     * @throws DeletedPaymentMethodException
     * @throws TransactionApprovalException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    protected function run(): OneTimePaymentTransactionApprovalResult
    {
        $pg_bill_key = PaymentMethodAppService::getOneTimePaymentPgBillKey($this->transaction->getPaymentMethodId());
        $pg_response = $this->pg_handler->approveTransaction($this->transaction, $pg_bill_key, $this->buyer);
        if (!$pg_response->isSuccess()) {
            $this->createTransactionHistory($pg_response);

            throw new TransactionApprovalException($pg_response->getResponseMessage());
        }
        
        $this->makeTransactionApproved($pg_response);

        return new OneTimePaymentTransactionApprovalResult(
            $this->transaction->getUuid()->toString(),
            $this->transaction->getPartnerTransactionId(),
            $this->transaction->getProductName(),
            $this->transaction->getAmount(),
            $this->transaction->getReservedAt(),
            $this->transaction->getApprovedAt()
        );
    }

    /**
     * @return OneTimePaymentTransactionApprovalResult
     */
    protected function getResult(): OneTimePaymentTransactionApprovalResult
    {
        $content = json_decode($this->getSerializedResult());

        return new OneTimePaymentTransactionApprovalResult(
            $content->transaction_id,
            $content->partner_transaction_id,
            $content->product_name,
            (int) $content->amount,
            \DateTime::createFromFormat(DATE_ATOM, $content->reserved_at),
            \DateTime::createFromFormat(DATE_ATOM, $content->approved_at)
        );
    }

    /**
     * @param string $transaction_uuid
     * @return TransactionEntity
     * @throws AlreadyCancelledTransactionException
     * @throws NotFoundTransactionException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private static function getTransaction(string $transaction_uuid): TransactionEntity
    {
        $transaction = TransactionRepository::getRepository()->findOneByUuid(Uuid::fromString($transaction_uuid));
        if (is_null($transaction)) {
            throw new NotFoundTransactionException();
        }
        self::assertApprovableTransaction($transaction);

        return $transaction;
    }

    /**
     * @param TransactionEntity $transaction
     * @throws AlreadyCancelledTransactionException
     */
    private static function assertApprovableTransaction(TransactionEntity $transaction): void
    {
        if ($transaction->isCanceled()) {
            throw new AlreadyCancelledTransactionException();
        }
    }

    /**
     * @param TransactionApprovalResponse $pg_response
     * @return TransactionHistoryEntity
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private function createTransactionHistory(TransactionApprovalResponse $pg_response): TransactionHistoryEntity
    {
        $transaction_history = TransactionHistoryEntity::createApproveHistory(
            $this->transaction,
            $pg_response->isSuccess(),
            $pg_response->getResponseCode(),
            $pg_response->getResponseMessage()
        );
        TransactionHistoryRepository::getRepository()->save($transaction_history);

        return $transaction_history;
    }

    /**
     * @param TransactionApprovalResponse $pg_response
     * @throws TransactionApprovalException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    private function makeTransactionApproved(TransactionApprovalResponse $pg_response): void
    {
        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $this->transaction->approve($pg_response->getPgTransactionId());
            TransactionRepository::getRepository()->save($this->transaction);

            $this->createTransactionHistory($pg_response);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            $this->refundPgTransaction();

            throw $t;
        }
    }

    /**
     * 결제 승인 건 환불 처리
     *
     * @throws TransactionApprovalException
     */
    private function refundPgTransaction(): void
    {
        // 결제 승인 건 취소 처리
        $cancel_reason = '[단건 결제] PG사 결제 성공 후, 내부 승인 처리 중 오류 발생';
        $cancel_transaction_response = $this->pg_handler->cancelTransaction(
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
    }
}
