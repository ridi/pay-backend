<?php
declare(strict_types=1);

namespace RidiPay\Tests\Pg\Kcp;

use AspectMock\Test;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RidiPay\Library\Pg\Kcp\CancelTransactionResponse;
use RidiPay\Library\Pg\Kcp\Card;
use RidiPay\Library\Pg\Kcp\Client;
use RidiPay\Library\Pg\Kcp\Company;
use RidiPay\Library\Pg\Kcp\Order;
use RidiPay\Library\Pg\Kcp\Response as KcpResponse;
use RidiPay\Library\Pg\Kcp\UnderMinimumPaymentAmountException;
use RidiPay\Library\Pg\Kcp\Util;

class KcpClientTest extends TestCase
{
    private const DUMMY_CARD_COMPANY = Company::KOOKMIN;
    private const DUMMY_CARD_NUMBER_KOOKMIN_CARD = '5164530000000000';
    private const DUMMY_CARD_EXPIRY_MAX = '7912';
    private const DUMMY_CARD_PASSWORD = '00';
    private const DUMMY_CARD_TAX_ID = '000101';

    private const DUMMY_ORDER_GOOD_NAME = '테스트 상품';
    private const DUMMY_ORDER_BUYER_NAME = '테스트 구매자';
    private const DUMMY_ORDER_BUYER_EMAIL = 'test@example.com';
    private const DUMMY_ORDER_BUYER_TEL1 = '02-000-0000';
    private const DUMMY_ORDER_BUYER_TEL2 = '010-0000-0000';

    /**
     * @dataProvider cardAndOrderProvider
     *
     * @param Card $card
     * @param Order $order
     */
    public function testPaymentLifecycle(Card $card, Order $order)
    {
        $client = Client::create();

        $guzzle_client = Test::double(
            GuzzleClient::class,
            [
                'request' => new Response(
                    200,
                    [],
                    json_encode([
                        'code' => KcpResponse::OK,
                        'message' => '',
                        'card_code' => self::DUMMY_CARD_COMPANY,
                        'card_name' => Company::getKoreanName(self::DUMMY_CARD_COMPANY),
                        'batch_key' => 'abcdefghijklmnopqrstuvwxyz'
                    ])
                )
            ]
        );
        $auth_res = $client->requestBatchKey($card);
        $this->assertTrue($auth_res->isSuccess());
        $this->assertSame(self::DUMMY_CARD_COMPANY, $auth_res->getCardCd());
        $this->assertSame(Company::getKoreanName($auth_res->getCardCd()), $auth_res->getCardName());
        Test::clean($guzzle_client);

        $guzzle_client = Test::double(
            GuzzleClient::class,
            [
                'request' => new Response(
                    200,
                    [],
                    json_encode([
                        'code' => KcpResponse::OK,
                        'message' => '',
                        'tno' => uniqid(),
                        'order_no' => $order->getId(),
                        'ca_order_id' => $order->getId(),
                        'amount' => $order->getGoodPrice(),
                        'card_amount' => $order->getGoodPrice(),
                        'coupon_amount' => 0,
                        'quota' => 0,
                        'tax_amount' => 90,
                        'tax_free_amount' => 0,
                        'vat_amount' => 10,
                        'is_interest_free' => false,
                        'escw_yn' => false,
                        'is_escrow' => false,
                        'card_code' => self::DUMMY_CARD_COMPANY,
                        'card_name' => Company::getKoreanName(self::DUMMY_CARD_COMPANY),
                        'acquirer_code' => Company::getAcquirerFromIssuer(self::DUMMY_CARD_COMPANY),
                        'acquirer_name' => Company::getKoreanName(Company::getAcquirerFromIssuer(self::DUMMY_CARD_COMPANY)),
                        'card_no' => self::DUMMY_CARD_NUMBER_KOOKMIN_CARD,
                        'approval_time' => (new \DateTime())->format('YmdHis'),
                    ])
                )
            ]
        );
        $order_res = $client->batchOrder($auth_res->getBatchKey(), $order);
        $this->assertTrue($order_res->isSuccess());
        $this->assertSame($order->getId(), $order_res->getOrderNo());
        $this->assertSame($order->getId(), $order_res->getCaOrderId());
        $this->assertSame($order->getGoodPrice(), $order_res->getAmount());
        $this->assertSame($order->getGoodPrice(), $order_res->getCardMny());
        $this->assertSame(0, $order_res->getCouponMny());
        $this->assertSame(0, $order_res->getQuota());
        $this->assertSame(90, $order_res->getResTaxMny());
        $this->assertSame(10, $order_res->getResVatMny());
        $this->assertSame(0, $order_res->getResFreeMny());
        $this->assertFalse($order_res->isNoinf());
        $this->assertFalse($order_res->isEscwYn());
        $this->assertSame(self::DUMMY_CARD_COMPANY, $order_res->getCardCd());
        $this->assertSame(Company::getKoreanName($order_res->getCardCd()), $order_res->getCardName());
        $this->assertSame(Company::getAcquirerFromIssuer(self::DUMMY_CARD_COMPANY), $order_res->getAcquCd());
        $this->assertSame(Company::getKoreanName($order_res->getAcquCd()), $order_res->getAcquName());
        $this->assertSame(self::DUMMY_CARD_NUMBER_KOOKMIN_CARD, $order_res->getCardNo());
        Test::clean($guzzle_client);

        $kcp_tno = $order_res->getTno();
        $guzzle_client = Test::double(
            GuzzleClient::class,
            [
                'request' => new Response(
                    200,
                    [],
                    json_encode([
                        'code' => KcpResponse::OK,
                        'message' => '',
                        'tno' => $kcp_tno,
                        'order_no' => $order->getId(),
                        'ca_order_id' => $order->getId(),
                        'amount' => $order->getGoodPrice(),
                        'card_amount' => $order->getGoodPrice(),
                        'coupon_amount' => 0,
                        'quota' => 0,
                        'is_interest_free' => false,
                        'escw_yn' => false,
                        'is_escrow' => false,
                        'card_code' => self::DUMMY_CARD_COMPANY,
                        'card_name' => Company::getKoreanName(self::DUMMY_CARD_COMPANY),
                        'acquirer_code' => Company::getAcquirerFromIssuer(self::DUMMY_CARD_COMPANY),
                        'acquirer_name' => Company::getKoreanName(Company::getAcquirerFromIssuer(self::DUMMY_CARD_COMPANY)),
                        'card_no' => self::DUMMY_CARD_NUMBER_KOOKMIN_CARD,
                        'cancel_time' => (new \DateTime())->format('YmdHis'),
                    ])
                )
            ]
        );
        $cancel_res = $client->cancelTransaction($kcp_tno, 'test');
        $this->assertTrue($cancel_res->isSuccess());
        $this->assertSame($order->getId(), $cancel_res->getOrderNo());
        $this->assertSame($order->getId(), $cancel_res->getCaOrderId());
        $this->assertSame($kcp_tno, $cancel_res->getTno());
        $this->assertSame($order->getGoodPrice(), $cancel_res->getAmount());
        $this->assertSame($order->getGoodPrice(), $cancel_res->getCardMny());
        $this->assertSame(0, $cancel_res->getCouponMny());
        $this->assertSame(0, $cancel_res->getQuota());
        $this->assertFalse($cancel_res->isNoinf());
        $this->assertFalse($cancel_res->isEscwYn());
        $this->assertSame(self::DUMMY_CARD_COMPANY, $cancel_res->getCardCd());
        $this->assertSame(Company::getKoreanName($cancel_res->getCardCd()), $cancel_res->getCardName());
        $this->assertSame(Company::getAcquirerFromIssuer(self::DUMMY_CARD_COMPANY), $cancel_res->getAcquCd());
        $this->assertSame(Company::getKoreanName($cancel_res->getAcquCd()), $cancel_res->getAcquName());
        Test::clean($guzzle_client);

        $kcp_tno = $order_res->getTno();
        $guzzle_client = Test::double(
            GuzzleClient::class,
            [
                'request' => new Response(
                    200,
                    [],
                    json_encode([
                        'code' => CancelTransactionResponse::ALREADY_CANCELLED,
                        'message' => '',
                    ])
                )
            ]
        );
        $cancel_res = $client->cancelTransaction($kcp_tno, 'test');
        $this->assertFalse($cancel_res->isSuccess());
        $this->assertTrue($cancel_res->isAlreadyCancelled());
        Test::clean($guzzle_client);
    }

    public function testBuildReceiptUrl()
    {
        $this->assertSame(
            'https://admin8.kcp.co.kr/assist/bill.BillActionNew.do?cmd=card_bill&tno=kcp_tno&order_no=order_no&trade_mony=100',
            Util::buildReceiptUrl(
                'kcp_tno', 'order_no', 100
            )
        );
    }

    public function testMinimumOrderPricePolicy()
    {
        $this->expectException(UnderMinimumPaymentAmountException::class);
        new Order(
            Uuid::uuid4()->toString(),
            self::DUMMY_ORDER_GOOD_NAME,
            Order::GOOD_PRICE_KRW_MIN - 1,
            self::DUMMY_ORDER_BUYER_NAME,
            self::DUMMY_ORDER_BUYER_EMAIL,
            self::DUMMY_ORDER_BUYER_TEL1,
            self::DUMMY_ORDER_BUYER_TEL2
        );

        $this->expectNotToPerformAssertions();
        new Order(
            Uuid::uuid4()->toString(),
            self::DUMMY_ORDER_GOOD_NAME,
            Order::GOOD_PRICE_KRW_MIN,
            self::DUMMY_ORDER_BUYER_NAME,
            self::DUMMY_ORDER_BUYER_EMAIL,
            self::DUMMY_ORDER_BUYER_TEL1,
            self::DUMMY_ORDER_BUYER_TEL2
        );
    }

    /**
     * @return array
     * @throws UnderMinimumPaymentAmountException
     */
    public function cardAndOrderProvider(): array
    {
        return [
            [
                new Card(
                    self::DUMMY_CARD_NUMBER_KOOKMIN_CARD,
                    self::DUMMY_CARD_EXPIRY_MAX,
                    self::DUMMY_CARD_PASSWORD,
                    self::DUMMY_CARD_TAX_ID
                ),
                new Order(
                    Uuid::uuid4()->toString(),
                    self::DUMMY_ORDER_GOOD_NAME,
                    Order::GOOD_PRICE_KRW_MIN,
                    self::DUMMY_ORDER_BUYER_NAME,
                    self::DUMMY_ORDER_BUYER_EMAIL,
                    self::DUMMY_ORDER_BUYER_TEL1,
                    self::DUMMY_ORDER_BUYER_TEL2
                )
            ],
            [
                new Card(
                    self::DUMMY_CARD_NUMBER_KOOKMIN_CARD,
                    self::DUMMY_CARD_EXPIRY_MAX,
                    self::DUMMY_CARD_PASSWORD,
                    self::DUMMY_CARD_TAX_ID
                ),
                new Order(
                    Uuid::uuid4()->toString(),
                    '리디북스 전자책; echo $(pwd)',
                    Order::GOOD_PRICE_KRW_MIN,
                    'echo $(whoami)',
                    'kcp-test@ridi.com',
                    self::DUMMY_ORDER_BUYER_TEL1,
                    self::DUMMY_ORDER_BUYER_TEL2
                )
            ]
        ];
    }
}
