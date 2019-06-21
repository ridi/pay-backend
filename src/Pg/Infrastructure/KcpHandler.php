<?php
declare(strict_types=1);

namespace RidiPay\Pg\Infrastructure;

use GuzzleHttp\Exception\GuzzleException;
use RidiPay\Kernel;
use RidiPay\Library\Pg\Kcp\Card;
use RidiPay\Library\Pg\Kcp\Client;
use RidiPay\Library\Pg\Kcp\Order;
use RidiPay\Library\Pg\Kcp\UnderMinimumPaymentAmountException;
use RidiPay\Library\Pg\Kcp\Util;
use RidiPay\Pg\Domain\Service\Buyer;
use RidiPay\Pg\Domain\Service\CardRegistrationResponse;
use RidiPay\Pg\Domain\Service\PgHandlerInterface;
use RidiPay\Pg\Domain\Service\TransactionApprovalResponse;
use RidiPay\Pg\Domain\Service\TransactionCancellationResponse;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;

class KcpHandler implements PgHandlerInterface
{
    /** @var Client */
    private $client;

    /**
     * @return KcpHandler
     */
    public static function create(): KcpHandler
    {
        $client = Client::create();

        return new KcpHandler($client);
    }

    /**
     * @return KcpHandler
     */
    public static function createWithTaxDeduction(): KcpHandler
    {
        $client = Client::createWithTaxDeduction();

        return new KcpHandler($client);
    }


    /**
     * @param Client $client
     */
    private function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param string $card_number 카드 번호 16자리
     * @param string $card_password 카드 비밀번호 앞 2자리
     * @param string $card_expiration_date 카드 유효 기한 (YYMM)
     * @param string $tax_id 개인: 생년월일(YYMMDD) / 법인: 사업자 등록 번호 10자리
     * @return CardRegistrationResponse
     * @throws GuzzleException
     */
    public function registerCard(
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id
    ): CardRegistrationResponse {
        $card = new Card($card_number, $card_expiration_date, $card_password, $tax_id);
        $response = $this->client->requestBatchKey($card);

        return new CardRegistrationResponse(
            $response->isSuccess(),
            $response->getResCd(),
            $response->getResMsg(),
            ($response->isSuccess() ? $response->getBatchKey() : null),
            ($response->isSuccess() ? $response->getCardCd() : null)
        );
    }

    /**
     * @param TransactionEntity $transaction
     * @param string $pg_bill_key
     * @param Buyer $buyer
     * @return TransactionApprovalResponse
     * @throws GuzzleException
     * @throws UnderMinimumPaymentAmountException
     */
    public function approveTransaction(
        TransactionEntity $transaction,
        string $pg_bill_key,
        Buyer $buyer
    ): TransactionApprovalResponse {
        $order = new Order(
            $transaction->getUuid()->toString(),
            $transaction->getProductName(),
            $transaction->getAmount(),
            $buyer->getName(),
            $buyer->getEmail(),
            '',
            ''
        );
        $response = $this->client->batchOrder($pg_bill_key, $order);

        return new TransactionApprovalResponse(
            $response->isSuccess(),
            $response->getResCd(),
            $response->getResMsg(),
            ($response->isSuccess() ? $response->getTno() : null),
            ($response->isSuccess() ? $response->getAmount() : null),
            ($response->isSuccess() ? $response->getAppTime() : null)
        );
    }

    /**
     * @param string $pg_transaction_id
     * @param string $cancel_reason
     * @return TransactionCancellationResponse
     * @throws GuzzleException
     */
    public function cancelTransaction(string $pg_transaction_id, string $cancel_reason): TransactionCancellationResponse
    {
        $response = $this->client->cancelTransaction($pg_transaction_id, $cancel_reason);

        return new TransactionCancellationResponse(
            $response->isSuccess(),
            $response->getResCd(),
            $response->getResMsg(),
            ($response->isSuccess() ? $response->getAmount() : null),
            ($response->isSuccess() ? $response->getCancTime() : null)
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
            !Kernel::isDev()
        );
    }
}
