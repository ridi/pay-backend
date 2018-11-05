<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Pg\Domain\Exception\CardRegistrationException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Transaction\Application\Service\SubscriptionAppService;
use RidiPay\User\Application\Dto\CardDto;
use RidiPay\User\Domain\Exception\CardAlreadyExistsException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\UnauthorizedCardRegistrationException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;
use RidiPay\User\Domain\Service\CardRegistrationValidator;
use RidiPay\User\Domain\Service\CardService;
use RidiPay\User\Domain\Service\UserActionHistoryService;

class CardAppService
{
    /**
     * @param int $u_idx
     * @param string $card_number 카드 번호 16자리
     * @param string $card_password 카드 비밀번호 앞 2자리
     * @param string $card_expiration_date 카드 유효 기한 (YYMM)
     * @param string $tax_id 개인: 생년월일(YYMMDD) / 법인: 사업자 등록 번호 10자리
     * @throws CardAlreadyExistsException
     * @throws CardRegistrationException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function registerCard(
        int $u_idx,
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id
    ): void {
        $card_registration_validator = new CardRegistrationValidator($u_idx);
        $card_registration_validator->validate();

        CardService::registerCard(
            $u_idx,
            $card_number,
            $card_expiration_date,
            $card_password,
            $tax_id
        );
    }

    /**
     * @param int $u_idx
     * @param string $payment_method_id
     * @return CardDto
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function deleteCard(int $u_idx, string $payment_method_id): CardDto
    {
        UserAppService::validateUser($u_idx);

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

            UserActionHistoryService::logDeleteCard($u_idx);

            if (empty($payment_method_repo->getAvailablePaymentMethods($u_idx))) {
                UserAppService::initializePinEntryHistory($u_idx);
                UserAppService::deletePin($u_idx);
                UserAppService::deleteOnetouchPay($u_idx);
            }

            SubscriptionAppService::optoutFirstPartySubscriptions($u_idx, $payment_method->getId());

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }

        return new CardDto($payment_method->getCardForOneTimePayment());
    }

    /**
     * @param int $u_idx
     * @return CardDto
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnauthorizedCardRegistrationException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function finishCardRegistration(int $u_idx): CardDto
    {
        if (!CardService::isCardRegistrationInProgress($u_idx)) {
            throw new UnauthorizedCardRegistrationException();
        }

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            try {
                UserAppService::validateUser($u_idx);
            } catch (NotFoundUserException $e) {
                UserAppService::createUser($u_idx);
            }

            $payment_method = CardService::useRegisteredCard($u_idx);
            UserAppService::useCreatedPin($u_idx);
            UserAppService::useSavedOnetouchPaySetting($u_idx);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }

        // 단건 결제용 카드와 정기 결제용 카드는 연동 PG사 분기 가능성을 고려해서 나눠진 것 뿐이고,
        // 두 카드는 동일한 카드라서 단건 결제용 카드를 외부로 전달한다.
        return new CardDto($payment_method->getCardForOneTimePayment());
    }
}
