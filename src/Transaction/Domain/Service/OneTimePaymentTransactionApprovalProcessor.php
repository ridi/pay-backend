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
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Exception\AlreadyCancelledTransactionException;
use RidiPay\Transaction\Domain\Exception\NotFoundTransactionException;
use RidiPay\Transaction\Domain\Repository\TransactionRepository;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Domain\Exception\DeletedPaymentMethodException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;

class OneTimePaymentTransactionApprovalProcessor extends IdempotentTransactionApprovalProcessor
{
    use TransactionApprovalTrait;

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
        $this->pg_handler = Kernel::isDev()
            ? PgHandlerFactory::createWithTest($this->pg->name)
            : PgHandlerFactory::create($this->pg->name);
        $this->buyer = $buyer;

        parent::__construct("ONE_TIME_PAYMENT_TRANSACTION_APPROVAL:{$transaction_uuid}");
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
    protected function run(): TransactionApprovalResult
    {
        $payment_key = PaymentMethodAppService::getOneTimePaymentKey($this->transaction->getPaymentMethodId());
        $transaction = self::approveTransaction($this->transaction, $this->pg_handler, $payment_key, $this->buyer);

        return new OneTimePaymentTransactionApprovalResult($transaction);
    }

    /**
     * @return OneTimePaymentTransactionApprovalResult
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

        return new OneTimePaymentTransactionApprovalResult($transaction);
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
}
