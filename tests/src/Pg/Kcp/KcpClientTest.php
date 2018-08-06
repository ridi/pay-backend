<?php
declare(strict_types=1);

namespace RidiPay\Tests\Pg\Kcp;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RidiPay\Library\Pg\Kcp\Card;
use RidiPay\Library\Pg\Kcp\Client;
use RidiPay\Library\Pg\Kcp\Company;
use RidiPay\Library\Pg\Kcp\Order;
use RidiPay\Library\Pg\Kcp\Response;
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
        $this->assertSame(Response::OK, $auth_res['res_cd']);
        $this->assertSame($card_company, $auth_res['card_cd']);
        $this->assertSame(Company::getKoreanName($auth_res['card_cd']), $auth_res['card_name']);

        $order_res = $client->batchOrder($auth_res['batch_key'], $order);
        $this->assertSame(Response::OK, $order_res['res_cd']);
        $this->assertSame($order_id, $order_res['order_no']);
        $this->assertSame($order_id, $order_res['ca_order_id']);
        $this->assertSame((string) $order->getGoodPrice(), $order_res['amount']);
        $this->assertSame((string) $order->getGoodPrice(), $order_res['card_mny']);
        $this->assertSame('00', $order_res['quota']);
        $this->assertSame((string) 90, $order_res['res_tax_mny']);
        $this->assertSame((string) 10, $order_res['res_vat_mny']);
        $this->assertSame(Company::getAcquirerFromIssuer($card_company), $order_res['acqu_cd']);

        $kcp_tno = $order_res['tno'];
        $cancel_res = $client->cancelTransaction($kcp_tno, 'test');
        $this->assertSame(Response::OK, $cancel_res['res_cd']);
        $this->assertSame($order_id, $cancel_res['order_no']);
        $this->assertSame($order_id, $cancel_res['ca_order_id']);
        $this->assertSame((string) $order->getGoodPrice(), $cancel_res['amount']);
        $this->assertSame((string) $order->getGoodPrice(), $cancel_res['card_mny']);
        $this->assertSame('00', $cancel_res['quota']);

        $cancel_res = $client->cancelTransaction($kcp_tno, 'test');
        $this->assertSame(Response::ALREADY_CANCELLED, $cancel_res['res_cd']);
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
