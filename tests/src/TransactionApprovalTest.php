<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use AspectMock\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RidiPay\Library\Pg\Kcp\BatchOrderResponse;
use RidiPay\Library\Pg\Kcp\Client;
use RidiPay\Partner\Domain\Entity\PartnerEntity;
use RidiPay\Partner\Domain\Repository\PartnerRepository;
use RidiPay\Pg\Application\Service\PgAppService;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Pg\Domain\Service\Buyer;
use RidiPay\Pg\Domain\Service\PgHandlerFactory;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Repository\TransactionHistoryRepository;
use RidiPay\Transaction\Domain\Repository\TransactionRepository;
use RidiPay\Transaction\Domain\Service\TransactionApprovalTrait;

class TransactionApprovalTest extends TestCase
{
    use TransactionApprovalTrait;

    public function testFailedPgTransactionApproval()
    {
        $partner = new PartnerEntity('transaction-approval-trait', 'test@12345', true);
        PartnerRepository::getRepository()->save($partner);
        $pg = PgAppService::getActivePg();

        $u_idx = TestUtil::getRandomUidx();
        $card = TestUtil::registerCard($u_idx, '123456');

        $transaction = new TransactionEntity(
            $u_idx,
            $card->getId(),
            $pg->id,
            $partner->getId(),
            Uuid::uuid4()->toString(),
            '리디북스 전자책',
            10000,
            new \DateTime('now')
        );
        TransactionRepository::getRepository()->save($transaction);

        $kcp_error_code = '8824';
        $kcp_error_message = 'FORMAT ERROR(지불정보|배치결제|배치키)';
        $client = Test::double(
            Client::class,
            [
                'batchOrder' => new BatchOrderResponse([
                    'code' => $kcp_error_code,
                    'message' => $kcp_error_message,
                ]),
            ]
        );
        try {
            self::approveTransaction(
                $transaction,
                PgHandlerFactory::createWithTest($pg->name),
                'dummy',
                new Buyer('id', 'name', 'ridi-pay-test@ridi.com')
            );
        } catch (TransactionApprovalException $e) {
            $this->assertFalse(self::isTransactionApprovalRunning($u_idx));

            $transaction_history = TransactionHistoryRepository::getRepository()->findByTransactionId(
                $transaction->getId()
            );
            $this->assertNotEmpty($transaction_history);
            $this->assertFalse($transaction_history[0]->isSuccess());
            $this->assertSame($kcp_error_code, $transaction_history[0]->getPgResponseCode());
            $this->assertSame($kcp_error_message, $transaction_history[0]->getPgResponseMessage());
        }
        Test::clean($client);
    }
}