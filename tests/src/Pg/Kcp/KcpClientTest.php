<?php
declare(strict_types=1);

namespace RidiPay\Tests\Pg\Kcp;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RidiPay\Library\Pg\Kcp\Card;
use RidiPay\Library\Pg\Kcp\Client;
use RidiPay\Library\Pg\Kcp\Company;
use RidiPay\Library\Pg\Kcp\Order;
use RidiPay\Library\Pg\Kcp\Util;

class KcpClientTest extends TestCase
{
    const DUMMY_CARD_NUMBER_KBCARD = '9490940000000000';
    const DUMMY_CARD_EXPIRY_MAX = '7912';
    const DUMMY_CARD_PASSWORD = '00';
    const DUMMY_CARD_TAX_ID = '000101';

    const DUMMY_ORDER_GOOD_NAME = '테스트 상품';
    const DUMMY_ORDER_BUYER_NAME = '테스트 구매자';
    const DUMMY_ORDER_BUYER_EMAIL = 'test@example.com';
    const DUMMY_ORDER_BUYER_TEL1 = '02-000-0000';
    const DUMMY_ORDER_BUYER_TEL2 = '010-0000-0000';

    const LOG_DIR = '/tmp/kcp';

    public function testPaymentLifecycle()
    {
        $client = Client::getTestClient(self::LOG_DIR);

        $card = new Card(
            self::DUMMY_CARD_NUMBER_KBCARD,
            self::DUMMY_CARD_EXPIRY_MAX,
            self::DUMMY_CARD_PASSWORD,
            self::DUMMY_CARD_TAX_ID
        );
        $card_company = Company::KOOKMIN;

        $order_id = Uuid::uuid4()->toString();
        $order = new Order(
            $order_id,
            self::DUMMY_ORDER_GOOD_NAME,
            Order::GOOD_PRICE_KRW_MIN,
            self::DUMMY_ORDER_BUYER_NAME,
            self::DUMMY_ORDER_BUYER_EMAIL,
            self::DUMMY_ORDER_BUYER_TEL1,
            self::DUMMY_ORDER_BUYER_TEL2
        );

        $auth_res = $client->requestBatchKey($card);
        $this->assertTrue($auth_res->isSuccess());
        $this->assertSame($card_company, $auth_res->getCardCd());
        $this->assertSame(Company::getKoreanName($auth_res->getCardCd()), $auth_res->getCardName());

        $order_res = $client->batchOrder($auth_res->getBatchKey(), $order);
        $this->assertTrue($order_res->isSuccess());
        $this->assertSame($order_id, $order_res->getOrderNo());
        $this->assertSame($order->getGoodPrice(), $order_res->getAmount());
        $this->assertSame($order->getGoodPrice(), $order_res->getCardMny());
        $this->assertSame(0, $order_res->getQuota());
        $this->assertSame(90, $order_res->getResTaxMny());
        $this->assertSame(10, $order_res->getResVatMny());
        $this->assertSame(Company::getAcquirerFromIssuer($card_company), $order_res->getAcquCd());
        $this->assertSame(Company::getKoreanName($order_res->getAcquCd()), $order_res->getAcquName());

        $kcp_tno = $order_res->getTno();
        $cancel_res = $client->cancelTransaction($kcp_tno, 'test');
        $this->assertTrue($cancel_res->isSuccess());
        $this->assertSame($order_id, $cancel_res->getOrderNo());
        $this->assertSame($order->getGoodPrice(), $cancel_res->getAmount());
        $this->assertSame($order->getGoodPrice(), $cancel_res->getCardMny());
        $this->assertSame(0, $cancel_res->getQuota());

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
}
