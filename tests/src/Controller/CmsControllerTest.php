<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use AspectMock\Test;
use Ridibooks\OAuth2\Symfony\Provider\User;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Dto\PaymentMethodHistoryItemDtoFactory;
use RidiPay\User\Application\Service\CardAppService;
use RidiPay\User\Application\Service\EmailSender;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Component\HttpFoundation\Request;

class CmsControllerTest extends ControllerTestCase
{
    /**
     * @throws \Exception
     */
    public static function setUpBeforeClass()
    {
        TestUtil::setUpJwtDoubles();
        Test::double(EmailSender::class, ['send' => null]);
    }

    public static function tearDownAfterClass()
    {
        Test::clean(EmailSender::class);
        TestUtil::tearDownJwtDoubles();
    }

    public function testCardsHistory()
    {
        $u_idx = TestUtil::getRandomUidx();

        $payment_method_uuid = TestUtil::registerCard(
            $u_idx,
            '123456',
            TestUtil::CARD['CARD_NUMBER'],
            TestUtil::CARD['CARD_EXPIRATION_DATE'],
            TestUtil::CARD['CARD_PASSWORD'],
            TestUtil::TAX_ID
        );
        CardAppService::deleteCard(
            new User(json_encode([
                'result' => [
                    'id' => 'test',
                    'idx' => $u_idx,
                    'email' => 'cms-api-test@ridi.com',
                    'is_verified_adult' => true,
                ],
                'message' => '정상적으로 완료되었습니다.'
            ])),
            $payment_method_uuid
        );

        $client = self::createClient();
        $client->request(Request::METHOD_GET, "/users/{$u_idx}/cards/history");

        $history = json_decode($client->getResponse()->getContent());
        $this->assertSame(2, count($history));

        $this->assertSame($payment_method_uuid, $history[0]->payment_method_id);
        $this->assertSame(PaymentMethodHistoryItemDtoFactory::ACTION_DELETION, $history[0]->action);

        $this->assertSame($payment_method_uuid, $history[1]->payment_method_id);
        $this->assertSame(PaymentMethodHistoryItemDtoFactory::ACTION_REGISTRATION, $history[1]->action);
    }

    public function testPinUpdateHistory()
    {
        $u_idx = TestUtil::getRandomUidx();

        TestUtil::registerCard(
            $u_idx,
            '123456',
            TestUtil::CARD['CARD_NUMBER'],
            TestUtil::CARD['CARD_EXPIRATION_DATE'],
            TestUtil::CARD['CARD_PASSWORD'],
            TestUtil::TAX_ID
        );
        UserAppService::updatePin(
            new User(json_encode([
                'result' => [
                    'id' => 'test',
                    'idx' => $u_idx,
                    'email' => 'cms-api-test@ridi.com',
                    'is_verified_adult' => true,
                ],
                'message' => '정상적으로 완료되었습니다.'
            ])),
            '654321'
        );
        UserAppService::updatePin(
            new User(json_encode([
                'result' => [
                    'id' => 'test',
                    'idx' => $u_idx,
                    'email' => 'cms-api-test@ridi.com',
                    'is_verified_adult' => true,
                ],
                'message' => '정상적으로 완료되었습니다.'
            ])),
            '123456'
        );

        $client = self::createClient();
        $client->request(Request::METHOD_GET, "/users/{$u_idx}/pin/history");

        $history = json_decode($client->getResponse()->getContent());
        $this->assertSame(2, count($history));
    }
}
