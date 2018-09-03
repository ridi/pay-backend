<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Transaction\Service\Pg\PgHandlerFactory;
use RidiPay\User\Domain\Service\CardService;
use RidiPay\User\Domain\Service\UserActionHistoryService;
use RidiPay\User\Domain\Service\UserService;
use RidiPay\User\Domain\Entity\UserEntity;
use RidiPay\User\Domain\Exception\AlreadyHadCardException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\UnregisteredUserException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;
use RidiPay\Transaction\Repository\PgRepository;
use RidiPay\User\Domain\Repository\UserRepository;

class CardAppService
{
    /**
     * @param int $u_idx
     * @param string $card_number 카드 번호 16자리
     * @param string $card_password 카드 비밀번호 앞 2자리
     * @param string $card_expiration_date 카드 유효 기한 (YYMM)
     * @param string $tax_id 개인: 생년월일(YYMMDD) / 법인: 사업자 등록 번호 10자리
     * @return string
     * @throws AlreadyHadCardException
     * @throws \Throwable
     */
    public static function registerCard(
        int $u_idx,
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id
    ): string {
        $pg = PgRepository::getRepository()->findActiveOne();
        $pg_handler = PgHandlerFactory::create($pg->getName());

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            try {
                $user = UserService::getActiveUser($u_idx);
            } catch (UnregisteredUserException $e) {
                $user = new UserEntity($u_idx);
                UserRepository::getRepository()->save($user);
            }

            $payment_method_id = CardService::registerCard(
                $u_idx,
                $card_number,
                $card_expiration_date,
                $card_password,
                $tax_id,
                $pg->getId(),
                $pg_handler
            );
            UserActionHistoryService::logAddCard($user);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }

        return $payment_method_id;
    }

    /**
     * @param int $u_idx
     * @param string $payment_method_id
     * @throws LeavedUserException
     * @throws UnregisteredUserException
     * @throws UnregisteredPaymentMethodException
     * @throws \Throwable
     */
    public static function deleteCard(int $u_idx, string $payment_method_id): void
    {
        $user = UserService::getActiveUser($u_idx);

        $payment_method_repo = PaymentMethodRepository::getRepository();
        $payment_method = $payment_method_repo->findOneByUuid(Uuid::fromString($payment_method_id));
        if (is_null($payment_method)) {
            throw new UnregisteredPaymentMethodException();
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
}
