<?php
declare(strict_types=1);

namespace RidiPay\Tests\Pg\Kcp;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RidiPay\Library\Pg\Kcp\Card;
use RidiPay\Library\Pg\Kcp\Client;
use RidiPay\Library\Pg\Kcp\Company;
use RidiPay\Library\Pg\Kcp\Order;
use RidiPay\Library\Pg\Kcp\UnderMinimumPaymentAmountException;
use RidiPay\Library\Pg\Kcp\Util;

class KcpClientTest extends TestCase
{
    private const DUMMY_CARD_NUMBER_SHINHAN_CARD = '4499140000000000';
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
        // TODO KCP_HTTP_PROXY_HOST 가 개발/테스트인지 확인
        $client = Client::create();

        $card_company = Company::SHINHAN;

        $auth_res = $client->requestBatchKey($card);
        $this->assertTrue($auth_res->isSuccess());
        $this->assertSame($card_company, $auth_res->getCardCd());
        $this->assertSame(Company::getKoreanName($auth_res->getCardCd()), $auth_res->getCardName());

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
        $this->assertSame($card_company, $order_res->getCardCd());
        $this->assertSame(Company::getKoreanName($order_res->getCardCd()), $order_res->getCardName());
        $this->assertSame(Company::getAcquirerFromIssuer($card_company), $order_res->getAcquCd());
        $this->assertSame(Company::getKoreanName($order_res->getAcquCd()), $order_res->getAcquName());
        $this->assertSame(self::DUMMY_CARD_NUMBER_SHINHAN_CARD, $order_res->getCardNo());

        $kcp_tno = $order_res->getTno();
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
        $this->assertSame($card_company, $cancel_res->getCardCd());
        $this->assertSame(Company::getKoreanName($cancel_res->getCardCd()), $cancel_res->getCardName());
        $this->assertSame(Company::getAcquirerFromIssuer($card_company), $cancel_res->getAcquCd());
        $this->assertSame(Company::getKoreanName($cancel_res->getAcquCd()), $cancel_res->getAcquName());

        $cancel_res = $client->cancelTransaction($kcp_tno, 'test');
        $this->assertFalse($cancel_res->isSuccess());
        $this->assertTrue($cancel_res->isAlreadyCancelled());
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
                    self::DUMMY_CARD_NUMBER_SHINHAN_CARD,
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
                    self::DUMMY_CARD_NUMBER_SHINHAN_CARD,
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
