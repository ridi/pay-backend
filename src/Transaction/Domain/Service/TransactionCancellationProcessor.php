<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Kernel;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\SentryHelper;
use RidiPay\Pg\Application\Dto\PgDto;
use RidiPay\Pg\Application\Service\PgAppService;
use RidiPay\Pg\Domain\Exception\TransactionCancellationException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\Service\PgHandlerFactory;
use RidiPay\Pg\Domain\Service\PgHandlerInterface;
use RidiPay\Pg\Domain\Service\TransactionCancellationResponse;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;
use RidiPay\Transaction\Domain\Exception\AlreadyCancelledTransactionException;
use RidiPay\Transaction\Domain\Exception\NotFoundTransactionException;
use RidiPay\Transaction\Domain\Repository\TransactionHistoryRepository;
use RidiPay\Transaction\Domain\Repository\TransactionRepository;

class TransactionCancellationProcessor
{
    /** @var TransactionEntity */
    private $transaction;

    /** @var PgDto */
    private $pg;

    /** @var PgHandlerInterface */
    private $pg_handler;

    /**
     * @param string $transaction_uuid
     * @throws AlreadyCancelledTransactionException
     * @throws NotFoundTransactionException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function __construct(string $transaction_uuid)
    {
        $this->transaction = self::getTransaction($transaction_uuid);
        $this->pg = PgAppService::getPgById($this->transaction->getPgId());
        $this->pg_handler = Kernel::isDev()
            ? PgHandlerFactory::createWithTest($this->pg->name)
            : PgHandlerFactory::create($this->pg->name);
    }

    /**
     * @return TransactionCancellationResult
     * @throws TransactionCancellationException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public function process(): TransactionCancellationResult
    {
        $cancel_reason = '고객 결제 취소';
        $pg_response = $this->pg_handler->cancelTransaction($this->transaction->getPgTransactionId(), $cancel_reason);
        if (!$pg_response->isSuccess()) {
            $this->createTransactionHistory($pg_response);

            throw new TransactionCancellationException($pg_response->getResponseMessage());
        }

        $this->makeTransactionCancelled($pg_response);

        return new TransactionCancellationResult($this->transaction);
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
        self::assertCancellableTransaction($transaction);

        return $transaction;
    }

    /**
     * @param TransactionEntity $transaction
     * @throws AlreadyCancelledTransactionException
     */
    private static function assertCancellableTransaction(TransactionEntity $transaction): void
    {
        if ($transaction->isCanceled()) {
            throw new AlreadyCancelledTransactionException();
        }
    }

    /**
     * @param TransactionCancellationResponse $pg_response
     * @return TransactionHistoryEntity
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private function createTransactionHistory(TransactionCancellationResponse $pg_response): TransactionHistoryEntity
    {
        $transaction_history = TransactionHistoryEntity::createCancelHistory(
            $this->transaction,
            $pg_response->isSuccess(),
            $pg_response->getResponseCode(),
            $pg_response->getResponseMessage()
        );
        TransactionHistoryRepository::getRepository()->save($transaction_history);

        return $transaction_history;
    }

    /**
     * @param TransactionCancellationResponse $pg_response
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    private function makeTransactionCancelled(TransactionCancellationResponse $pg_response): void
    {
        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $this->transaction->cancel();
            TransactionRepository::getRepository()->save($this->transaction);

            $this->createTransactionHistory($pg_response);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            $data = [
                'extra' => [
                    'partner_transaction_id' => $this->transaction->getPartnerTransactionId(),
                    'transaction_id' => $this->transaction->getId(),
                    'pg_transaction_id' => $this->transaction->getPgTransactionId()
                ]
            ];
            SentryHelper::captureMessage('PG사 결제 취소 성공 후, 내부 취소 처리 중 오류 발생', [], $data, true);

            throw $t;
        }
    }
}
