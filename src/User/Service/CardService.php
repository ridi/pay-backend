<?php
declare(strict_types=1);

namespace RidiPay\User\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Transaction\Service\Pg\PgHandlerFactory;
use RidiPay\User\Constant\PaymentMethodTypeConstant;
use RidiPay\User\Entity\CardEntity;
use RidiPay\User\Entity\PaymentMethodEntity;
use RidiPay\User\Exception\AlreadyCardAddedException;
use RidiPay\User\Exception\LeavedUserException;
use RidiPay\User\Exception\NonUserException;
use RidiPay\User\Exception\UnknownPaymentMethodException;
use RidiPay\User\Repository\CardIssuerRepository;
use RidiPay\User\Repository\CardRepository;
use RidiPay\User\Repository\PaymentMethodRepository;
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
     * @return string
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
    ): string {
        self::assertNotHavingCard($u_idx);

        $pg = PgRepository::getRepository()->findActiveOne();
        $pg_processor = PgHandlerFactory::create($pg->getName(), $is_test);
        $response = $pg_processor->registerCard($card_number, $card_expiration_date, $card_password, $tax_id);
        $card_issuer = CardIssuerRepository::getRepository()->findOneByPgIdAndCode(
            $pg->getId(),
            $response->getCardIssuerCode()
        );

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $user = UserService::createUserIfNotExists($u_idx);

            $payment_method = new PaymentMethodEntity($user, PaymentMethodTypeConstant::CARD);
            PaymentMethodRepository::getRepository()->save($payment_method);

            $card_for_one_time_payment = CardEntity::createForOneTimePayment(
                $payment_method,
                $pg,
                $card_issuer,
                $card_number,
                $response->getPgBillKey()
            );
            $card_for_billing_payment = CardEntity::createForBillingPayment(
                $payment_method,
                $pg,
                $card_issuer,
                $card_number,
                $response->getPgBillKey()
            );
            $card_repo = CardRepository::getRepository();
            $card_repo->save($card_for_one_time_payment);
            $card_repo->save($card_for_billing_payment);

            UserActionHistoryService::logAddCard($user);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }

        return $payment_method->getUuid()->toString();
    }

    /**
     * @param int $u_idx
     * @param string $payment_method_id
     * @throws LeavedUserException
     * @throws NonUserException
     * @throws UnknownPaymentMethodException
     * @throws \Throwable
     */
    public static function deleteCard(
        int $u_idx,
        string $payment_method_id
    ) {
        $user = UserService::getUser($u_idx);

        $payment_method_repo = PaymentMethodRepository::getRepository();
        $payment_method = $payment_method_repo->findOneByUuid(Uuid::fromString($payment_method_id));
        if (is_null($payment_method)) {
            throw new UnknownPaymentMethodException();
        }

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $payment_method->delete();
            $payment_method_repo->save($payment_method);

            UserActionHistoryService::logDeleteCard($user);
            // TODO: first-party 정기 결제 해지 요청

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }
    }

    /**
     * @param int $u_idx
     * @throws AlreadyCardAddedException
     */
    private static function assertNotHavingCard(int $u_idx)
    {
        $payment_method_repo = PaymentMethodRepository::getRepository();
        $payment_methods = $payment_method_repo->getAvailablePaymentMethods($u_idx);

        if (!empty(array_filter(
            $payment_methods,
            function (PaymentMethodEntity $payment_method) {
                return $payment_method->isCard();
            }
        ))) {
            throw new AlreadyCardAddedException();
        }
    }
}
