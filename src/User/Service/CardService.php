<?php
declare(strict_types=1);

namespace RidiPay\User\Service;

use Ridibooks\Payment\Kcp\Card;
use Ridibooks\Payment\Kcp\Client;
use Ridibooks\Payment\Kcp\Response;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Constant\PaymentMethodTypeConstant;
use RidiPay\User\Entity\CardEntity;
use RidiPay\User\Entity\PaymentMethodEntity;
use RidiPay\User\Exception\AlreadyCardAddedException;
use RidiPay\User\Repository\CardIssuerRepository;
use RidiPay\User\Repository\CardRepository;
use RidiPay\User\Repository\PaymentMethodRepository;
use RidiPay\Transaction\Constant\PgConstant;
use RidiPay\Transaction\Repository\PgRepository;

class CardService
{
    /**
     * @param int $u_idx
     * @param string $card_number 카드 번호 16자리
     * @param string $card_password 카드 비밀번호 앞 2자리
     * @param string $card_expiration_date 카드 유효 기한 (YYMM)
     * @param string $tax_id 개인: 생년월일(YYMMDD) / 법인: 사업자 등록 번호 10자리
     * @param bool $is_test Bill Key 발급 시, PG사 테스트 서버 이용 여부
     * @throws AlreadyCardAddedException
     * @throws \Throwable
     */
    public static function addCard(
        int $u_idx,
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id,
        bool $is_test = false
    ) {
        self::assertNotHavingCard($u_idx);

        $response = self::requestBillKey($card_number, $card_expiration_date, $card_password, $tax_id, $is_test);
        $pg_bill_key = $response['batch_key'];
        $card_issuer_code = $response['card_cd'];

        $pg = PgRepository::getRepository()->findOneByName(PgConstant::KCP);
        $card_issuer = CardIssuerRepository::getRepository()->findOneByPgIdAndCode($pg->getId(), $card_issuer_code);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            if (!UserService::isUser($u_idx)) {
                UserService::createUser($u_idx);
            }

            $payment_method = new PaymentMethodEntity($u_idx, PaymentMethodTypeConstant::CARD);
            PaymentMethodRepository::getRepository()->save($payment_method);

            $card_for_one_time_payment = CardEntity::createForOneTimePayment(
                $card_number,
                $pg_bill_key,
                $payment_method,
                $pg,
                $card_issuer
            );
            $card_for_subscription_payment = CardEntity::createForSubscriptionPayment(
                $card_number,
                $pg_bill_key,
                $payment_method,
                $pg,
                $card_issuer
            );
            $card_repo = CardRepository::getRepository();
            $card_repo->save($card_for_one_time_payment);
            $card_repo->save($card_for_subscription_payment);

            UserActionHistoryService::logAddCard($u_idx);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }
    }

    /**
     * @param int $u_idx
     * @param string $payment_method_id
     * @throws \Exception
     * @throws \Throwable
     */
    public static function deleteCard(
        int $u_idx,
        string $payment_method_id
    ) {
        $payment_method_repo = PaymentMethodRepository::getRepository();
        $payment_method = $payment_method_repo->findOneByUuid($payment_method_id);
        if (is_null($payment_method)) {
            // TODO: 별도 Exception 클래스 throw
            throw new \Exception('등록되지 않은 결제 수단입니다.');
        }

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $payment_method->delete();
            $payment_method_repo->save($payment_method);

            UserActionHistoryService::logRemoveCard($u_idx);
            // TODO: first-party 정기 결제 해지 요청

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }
    }

    /**
     * @param string $card_number 카드 번호 16자리
     * @param string $card_password 카드 비밀번호 앞 2자리
     * @param string $card_expiration_date 카드 유효 기한 (YYMM)
     * @param string $tax_id 개인: 생년월일(YYMMDD) / 법인: 사업자 등록 번호 10자리
     * @param bool $is_test Bill Key 발급 시, PG사 테스트 서버 이용 여부
     * @throws \Throwable
     * @return array
     */
    private static function requestBillKey(
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id,
        bool $is_test
    ): array {
        // TODO: KCP 연동 값 채우기
        $site_code = '';
        $site_key = '';
        $group_id = '';
        $log_dir = '/app/var/log';

        if ($is_test) {
            $kcp = Client::getTestClient($log_dir);
        } else {
            $kcp = new Client($site_code, $site_key, $group_id, $log_dir);
        }

        $card = new Card($card_number, $card_expiration_date, $card_password, $tax_id);
        $response = $kcp->requestBatchKey($card);
        if ($response['res_cd'] !== Response::OK) {
            // TODO: 예외 처리
        }

        return $response;
    }

    /**
     * @param int $u_idx
     * @throws AlreadyCardAddedException
     */
    private static function assertNotHavingCard(int $u_idx)
    {
        $payment_method_repo = PaymentMethodRepository::getRepository();
        $payment_methods = $payment_method_repo->getPaymentMethods($u_idx);

        if (!empty(array_filter(
            $payment_methods,
            function (PaymentMethodEntity $payment_method) {
                return $payment_method->isCard();
            }
        ))) {
            throw new AlreadyCardAddedException('카드는 하나만 등록할 수 있습니다.');
        }
    }
}
