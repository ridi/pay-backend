<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use Ridibooks\OAuth2\Symfony\Provider\User;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\MailRenderer;
use RidiPay\Library\TimeUnitConstant;
use RidiPay\Library\ValidationTokenManager;
use RidiPay\Pg\Domain\Exception\CardRegistrationException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Transaction\Application\Service\SubscriptionAppService;
use RidiPay\Transaction\Domain\Exception\AlreadyCancelledSubscriptionException;
use RidiPay\Transaction\Domain\Service\TransactionApprovalTrait;
use RidiPay\User\Application\Dto\CardDto;
use RidiPay\User\Domain\Exception\DeletedPaymentMethodException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\PaymentMethodChangeDeclinedException;
use RidiPay\User\Domain\Exception\UnauthorizedCardRegistrationException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;
use RidiPay\User\Domain\Service\CardService;
use RidiPay\User\Domain\Service\UserActionHistoryService;

class CardAppService
{
    use TransactionApprovalTrait;

    /**
     * @param int $u_idx
     * @param string $card_number 카드 번호 16자리
     * @param string $card_password 카드 비밀번호 앞 2자리
     * @param string $card_expiration_date 카드 유효 기한 (YYMM)
     * @param string $tax_id 개인: 생년월일(YYMMDD) / 법인: 사업자 등록 번호 10자리
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
        CardService::registerCard(
            $u_idx,
            $card_number,
            $card_expiration_date,
            $card_password,
            $tax_id
        );
    }

    /**
     * @param User $oauth2_user
     * @param string $payment_method_uuid
     * @throws AlreadyCancelledSubscriptionException
     * @throws DeletedPaymentMethodException
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function deleteCard(User $oauth2_user, string $payment_method_uuid)
    {
        $u_idx = $oauth2_user->getUidx();
        UserAppService::validateUser($u_idx);

        $payment_method_id = PaymentMethodAppService::getPaymentMethodIdByUuid($payment_method_uuid);
        if ($u_idx !== PaymentMethodAppService::getUidxById($payment_method_id)) {
            throw new UnregisteredPaymentMethodException();
        }

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $payment_method_repo = PaymentMethodRepository::getRepository();
            $payment_method = $payment_method_repo->findOneById($payment_method_id);
            $payment_method->delete();
            $payment_method_repo->save($payment_method);

            UserActionHistoryService::logDeleteCard($u_idx);

            $available_payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($u_idx);
            if (empty($available_payment_methods->cards)) {
                UserAppService::initializePinEntryHistory($u_idx);
                UserAppService::deletePin($u_idx);
                UserAppService::deleteOnetouchPay($u_idx);
            }

            SubscriptionAppService::optoutSubscriptions($u_idx, $payment_method_id);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }

        $card = new CardDto($payment_method->getCardForOneTimePayment());
        $data = [
            'card_issuer_name' => $card->issuer_name,
            'iin' => $card->iin
        ];
        $email_body = (new MailRenderer())->render('card_deletion_alert.twig', $data);
        EmailSender::send(
            $oauth2_user->getEmail(),
            "{$oauth2_user->getUid()}님, 카드 삭제 안내드립니다.",
            $email_body
        );
    }

    /**
     * @param User $oauth2_user
     * @return CardDto
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnauthorizedCardRegistrationException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function finishCardRegistration(User $oauth2_user): CardDto
    {
        $u_idx = $oauth2_user->getUidx();

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
            UserActionHistoryService::logRegisterCard($u_idx);

            UserAppService::useCreatedPin($u_idx);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }

        $card = new CardDto($payment_method->getCardForOneTimePayment());
        $data = [
            'card_issuer_name' => $card->issuer_name,
            'iin' => $card->iin
        ];
        $email_body = (new MailRenderer())->render('card_registration_alert.twig', $data);
        EmailSender::send(
            $oauth2_user->getEmail(),
            "{$oauth2_user->getUid()}님, 카드 등록 안내드립니다.",
            $email_body
        );

        return $card;
    }

    /**
     * @param User $oauth2_user
     * @return CardDto
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws PaymentMethodChangeDeclinedException
     * @throws UnauthorizedCardRegistrationException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public static function changeCard(User $oauth2_user): CardDto
    {
        UserAppService::validateUser($oauth2_user->getUidx());
        if (!CardService::isCardRegistrationInProgress($oauth2_user->getUidx())) {
            throw new UnauthorizedCardRegistrationException();
        }
        if (self::isTransactionApprovalRunning($oauth2_user->getUidx())) {
            throw new PaymentMethodChangeDeclinedException();
        }

        $em = EntityManagerProvider::getEntityManager();
        $new_payment_method = $em->transactional(function () use ($oauth2_user) {
            // 기존 카드 삭제
            $payment_method_repo = PaymentMethodRepository::getRepository();
            $previous_payment_methods = $payment_method_repo->getAvailablePaymentMethods($oauth2_user->getUidx());
            foreach ($previous_payment_methods as $previous_payment_method) {
                $previous_payment_method->delete();
                $payment_method_repo->save($previous_payment_method, true);
            }

            UserActionHistoryService::logChangeCard($oauth2_user->getUidx());

            // 신규 카드 등록
            $new_payment_method = CardService::useRegisteredCard($oauth2_user->getUidx());
            UserAppService::useCreatedPin($oauth2_user->getUidx());

            // 기존 카드로부터 신규 카드로 구독 이전
            foreach ($previous_payment_methods as $previous_payment_method) {
                SubscriptionAppService::changePaymentMethod(
                    $previous_payment_method->getId(),
                    $new_payment_method->getId()
                );
            }

            return $new_payment_method;
        });

        $card = new CardDto($new_payment_method->getCardForOneTimePayment());
        $data = [
            'card_issuer_name' => $card->issuer_name,
            'iin' => $card->iin,
            'subscriptions' => $card->subscriptions
        ];
        $email_body = (new MailRenderer())->render('card_change_alert.twig', $data);
        EmailSender::send(
            $oauth2_user->getEmail(),
            "{$oauth2_user->getUid()}님, 카드 변경 안내드립니다.",
            $email_body
        );

        return $card;
    }

    /**
     * @param int $u_idx
     * @return string
     * @throws \Exception
     */
    public static function generateValidationToken(int $u_idx): string
    {
        return ValidationTokenManager::generate(
            self::getCardRegistrationKey($u_idx),
            5 * TimeUnitConstant::SEC_IN_MINUTE
        );
    }

    /**
     * @param int $u_idx
     * @return string
     */
    public static function getCardRegistrationKey(int $u_idx): string
    {
        return "card-registration:{$u_idx}";
    }
}
