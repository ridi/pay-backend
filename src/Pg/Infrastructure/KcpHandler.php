<?php
declare(strict_types=1);

namespace RidiPay\Pg\Infrastructure;

use RidiPay\Library\Log\StdoutLogger;
use RidiPay\Library\Pg\Kcp\Card;
use RidiPay\Library\Pg\Kcp\Client;
use RidiPay\Library\Pg\Kcp\Order;
use RidiPay\Library\Pg\Kcp\Response;
use RidiPay\Library\Pg\Kcp\Util;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Pg\Domain\Exception\PgException;
use RidiPay\Pg\Domain\Service\ApproveTransactionResponse;
use RidiPay\Pg\Domain\Service\CancelTransactionResponse;
use RidiPay\Pg\Domain\Service\PgHandlerInterface;
use RidiPay\Pg\Domain\Service\RegisterCardResponse;
use RidiPay\User\Application\Service\PaymentMethodAppService;

class KcpHandler implements PgHandlerInterface
{
    /** @var bool */
    private $is_dev;

    /** @var Client */
    private $client;

    public function __construct()
    {
        $this->is_dev = getenv('APP_ENV') === 'dev';

        if ($this->is_dev) {
            $this->client = Client::getTestClient();
        } else {
            $this->client = new Client(getenv('KCP_SITE_CODE'), getenv('KCP_SITE_KEY'), getenv('KCP_GROUP_ID'));
        }
    }

    /**
     * @param string $card_number 카드 번호 16자리
     * @param string $card_password 카드 비밀번호 앞 2자리
     * @param string $card_expiration_date 카드 유효 기한 (YYMM)
     * @param string $tax_id 개인: 생년월일(YYMMDD) / 법인: 사업자 등록 번호 10자리
     * @return RegisterCardResponse
     * @throws PgException
     */
    public function registerCard(
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id
    ): RegisterCardResponse {
        $card = new Card($card_number, $card_expiration_date, $card_password, $tax_id);

        $response = $this->client->requestBatchKey($card);
        if (!$response->isSuccess()) {
            self::log(__METHOD__, $response);

            throw new PgException('KCP Batch Key 발급 실패');
        }

        return new RegisterCardResponse(
            $response->isSuccess(),
            $response->getResCd(),
            $response->getResMsg(),
            $response->getBatchKey(),
            $response->getCardCd()
        );
    }

    /**
     * @param TransactionEntity $transaction
     * @return ApproveTransactionResponse
     * @throws PgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function approveTransaction(TransactionEntity $transaction): ApproveTransactionResponse
    {
        // TODO: 아래 값 필요 여부 확인
        $buyer_name = '';
        $buyer_email = '';
        $buyer_tel1 = '';
        $buyer_tel2 = '';

        $order = new Order(
            $transaction->getUuid()->toString(),
            $transaction->getProductName(),
            $transaction->getAmount(),
            $buyer_name,
            $buyer_email,
            $buyer_tel1,
            $buyer_tel2
        );
        $pg_bill_key = PaymentMethodAppService::getOneTimePaymentPgBillKey($transaction->getPaymentMethodId());

        $response = $this->client->batchOrder($pg_bill_key, $order);
        if (!$response->isSuccess()) {
            self::log(__METHOD__, $response);

            throw new PgException('KCP 결제 승인 실패');
        }

        return new ApproveTransactionResponse(
            $response->isSuccess(),
            $response->getResCd(),
            $response->getResMsg(),
            $response->getTno(),
            $response->getAmount(),
            $response->getAppTime()
        );
    }

    /**
     * @param string $pg_transaction_id
     * @param string $cancel_reason
     * @return CancelTransactionResponse
     * @throws PgException
     */
    public function cancelTransaction(string $pg_transaction_id, string $cancel_reason): CancelTransactionResponse
    {
        $response = $this->client->cancelTransaction($pg_transaction_id, $cancel_reason);
        if (!$response->isSuccess()) {
            self::log(__METHOD__, $response);

            throw new PgException('KCP 결제 취소 실패');
        }

        return new CancelTransactionResponse(
            $response->isSuccess(),
            $response->getResCd(),
            $response->getResMsg(),
            $response->getAmount(),
            $response->getCancTime()
        );
    }

    /**
     * @param TransactionEntity $transaction
     * @return string
     */
    public function getCardReceiptUrl(TransactionEntity $transaction): string
    {
        return Util::buildReceiptUrl(
            $transaction->getPgTransactionId(),
            $transaction->getUuid()->toString(),
            $transaction->getAmount(),
            Util::RECEIPT_LANG_KO,
            !$this->is_dev
        );
    }

    /**
     * @param string $name
     * @param Response $response
     * @throws \Exception
     */
    private static function log(string $name, Response $response): void
    {
        $logger = new StdoutLogger($name);
        $logger->error(json_encode(['res_cd' => $response->getResCd(), 'res_msg' => $response->getResMsg()]));
    }
}
