<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;

use \GuzzleHttp\Client as HttpClient;
use \Symfony\Component\HttpFoundation\Response;

/**
 * NHN KCP 결제 모듈을 래핑한 클라이언트입니다.
 *
 * 주의: 연관 배열들의 키 순서를 바꾸지 마세요! PHP 연관 배열은 순서가 매겨진 맵이고, 결제 모듈 호출시 파라미터 순서가 유지되어야 합니다.
 *
 * 정의된 상수들은 임의로 변경할 수 없습니다.
 */
class Client
{

    const MODE_PRODUCTION = 'prd';
    const MODE_PRODUCTION_TAX_DEDUCTION = 'ptx';
    const MODE_DEVELOPMENT = 'dev';

    /** @var HttpClient */
    private $http_client;

    /** @var string */
    private $mode;

    /**
     * Client constructor.
     * @param string $mode
     */
    public function __construct(string $mode = self::MODE_DEVELOPMENT) {
        $this->http_client = new HttpClient([
            'base_uri' => getenv('KCP_HTTP_PROXY_HOST'),
            'connect_timeout' => 10,
            'timeout' => 10,
            'headers' => [ 'Content-Type' => 'application/json' ]
        ]);
        $this->mode = $mode;
    }

    /**
     * @param Card $card
     * @return BatchKeyResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function requestBatchKey(Card $card): BatchKeyResponse
    {
        $data = [
            'mode' => $this->mode,
            'card_no' => $card->getNumber(),
            'card_expiry_date' => $card->getExpiry(),
            'card_tax_no' => $card->getTaxId(),
            'card_password' => $card->getPassword()
        ];

        $response = $this->http_client->request('POST','/kcp/payments/auth-key', [ 'body' => json_encode($data) ]);
        $response->getStatusCode();
        // TODO timeout 일 때 요청에 대한 재시도를 시도할 수 있도록 RETRY 관리할 것

        if ($response->getStatusCode() !== Response::HTTP_CREATED)  {
            // TODO handle error
        }
        $decoded_response = @json_decode($response->getBody()->getContents(), true);

        return new BatchKeyResponse($decoded_response);
    }

    /**
     * @param string $batch_key
     * @param Order $order
     * @param int $installment_months
     * @return BatchOrderResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function batchOrder(string $batch_key, Order $order, int $installment_months = 0): BatchOrderResponse
    {
        $data = [
            'mode' => $this->mode,
            'bill_key' => $batch_key,
            'order_no' => $order->getId(),
            'product_name' => $order->getGoodName(),
            'product_amount'=> $order->getGoodPrice(),
            'buyer_name' => $order->getBuyerName(),
            'buyer_email' => $order->getBuyerEmail(),
            'installment_months' => $installment_months
        ];

        $response = $this->http_client->request('POST','/kcp/payments', [ 'body' => json_encode($data) ]);
        $response->getStatusCode();

        if ($response->getStatusCode() !== Response::HTTP_OK)  {
            // TODO handle error
        }
        $decoded_response = @json_decode($response->getBody()->getContents(), true);

        return new BatchOrderResponse($decoded_response);
    }

    /**
     * @param string $kcp_tno
     * @param string $reason
     * @return CancelTransactionResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function cancelTransaction(string $kcp_tno, string $reason): CancelTransactionResponse
    {
        $data = [
            'mode' => $this->mode,
            'reason' => $reason
        ];

        $response = $this->http_client->request('DELETE',"/kcp/payments/${kcp_tno}", [ 'body' => json_encode($data) ]);
        $response->getStatusCode();

        if ($response->getStatusCode() !== Response::HTTP_OK)  {
            // TODO handle error
        }
        $decoded_response = @json_decode($response->getBody()->getContents(), true);

        return new CancelTransactionResponse($decoded_response);
    }
}
