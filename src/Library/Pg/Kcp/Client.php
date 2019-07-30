<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\Request;

class Client
{
    /** @var int  */
    private const TIMEOUT_IN_SECONDS = 10;

    /** @var HttpClient */
    private $http_client;

    /** @var bool */
    private $is_tax_deductible;

    /**
     * @return Client
     */
    public static function create(): Client
    {
        return new Client(false);
    }

    /**
     * @return Client
     */
    public static function createWithTaxDeduction(): Client
    {
        return new Client(true);
    }

    /**
     * @param bool $is_tax_deductible
     */
    private function __construct(bool $is_tax_deductible = false)
    {
        $this->http_client = new HttpClient([
            'base_uri' => getenv('KCP_HTTP_PROXY_URL'),
            'connect_timeout' => self::TIMEOUT_IN_SECONDS,
            'timeout' => self::TIMEOUT_IN_SECONDS,
            'headers' => ['Content-Type' => 'application/json'],
            'http_errors' => false,
        ]);
        $this->is_tax_deductible = $is_tax_deductible;
    }

    /**
     * @param Card $card
     * @return BatchKeyResponse
     * @throws GuzzleException
     */
    public function requestBatchKey(Card $card): BatchKeyResponse
    {
        $data = [
            'is_tax_deductible' => $this->is_tax_deductible,
            'card_no' => $card->getNumber(),
            'card_expiry_date' => $card->getExpiry(),
            'card_tax_no' => $card->getTaxId(),
            'card_password' => $card->getPassword()
        ];

        $response = $this->http_client->request(
            Request::METHOD_POST,
            '/payments/batch-key',
            ['body' => \json_encode($data)]
        );
        $decoded_response = \json_decode($response->getBody()->getContents(), true);
        return new BatchKeyResponse($decoded_response);
    }

    /**
     * @param string $batch_key
     * @param Order $order
     * @param int $installment_months
     * @return BatchOrderResponse
     * @throws GuzzleException
     */
    public function batchOrder(string $batch_key, Order $order, int $installment_months = 0): BatchOrderResponse
    {
        $data = [
            'is_tax_deductible' => $this->is_tax_deductible,
            'batch_key' => $batch_key,
            'order_no' => $order->getId(),
            'product_name' => $order->getGoodName(),
            'product_amount'=> $order->getGoodPrice(),
            'buyer_name' => $order->getBuyerName(),
            'buyer_email' => $order->getBuyerEmail(),
            'installment_months' => $installment_months
        ];

        $response = $this->http_client->request(
            Request::METHOD_POST,
            '/payments',
            ['body' => \json_encode($data)]
        );
        $decoded_response = \json_decode($response->getBody()->getContents(), true);
        return new BatchOrderResponse($decoded_response);
    }

    /**
     * @param string $kcp_tno
     * @param string $reason
     * @return CancelTransactionResponse
     * @throws GuzzleException
     */
    public function cancelTransaction(string $kcp_tno, string $reason): CancelTransactionResponse
    {
        $data = [
            'is_tax_deductible' => $this->is_tax_deductible,
            'reason' => $reason
        ];

        $response = $this->http_client->request(
            Request::METHOD_DELETE,
            "/payments/${kcp_tno}",
            ['body' => \json_encode($data)]
        );
        $decoded_response = \json_decode($response->getBody()->getContents(), true);
        return new CancelTransactionResponse($decoded_response);
    }
}
