<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\Log\StdoutLogger;
use RidiPay\Library\TimeUnitConstant;
use RidiPay\Library\ValidationTokenManager;
use RidiPay\User\Application\Dto\UserInformationDto;
use RidiPay\User\Domain\Entity\UserEntity;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\OnetouchPaySettingChangeDeclinedException;
use RidiPay\User\Domain\Exception\PinEntryBlockedException;
use RidiPay\User\Domain\Exception\UnchangedPinException;
use RidiPay\User\Domain\Exception\UnmatchedPinException;
use RidiPay\User\Domain\Exception\UnauthorizedPinChangeException;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;
use RidiPay\User\Domain\Exception\WrongFormattedPinException;
use RidiPay\User\Domain\Repository\UserRepository;
use RidiPay\User\Domain\Service\AbuseBlocker;
use RidiPay\User\Domain\Service\CardService;
use RidiPay\User\Domain\Service\PinEntryAbuseBlockPolicy;
use RidiPay\User\Domain\Service\UserActionHistoryService;

class UserAppService
{
    /**
     * @param int $u_idx
     * @return UserInformationDto
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnsupportedPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getUserInformation(int $u_idx): UserInformationDto
    {
        $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($u_idx);
        $user = self::getUser($u_idx);

        return new UserInformationDto($payment_methods, $user);
    }

    /**
     * @param int $u_idx
     * @param string $pin
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws WrongFormattedPinException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function createPin(int $u_idx, string $pin): void
    {
        $user = self::getUser($u_idx);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $user->createPin($pin);
            UserRepository::getRepository()->save($user);

            UserActionHistoryService::logCreatePin($u_idx);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            $logger = new StdoutLogger(__METHOD__);
            $logger->error($t->getMessage());

            throw $t;
        }
    }

    /**
     * @param int $u_idx
     * @param string $pin
     * @param string $validation_token
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnauthorizedPinChangeException
     * @throws UnchangedPinException
     * @throws WrongFormattedPinException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function updatePin(int $u_idx, string $pin, string $validation_token): void
    {
        $user = self::getUser($u_idx);
        self::assertMatchedValidationToken($u_idx, $validation_token);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $user->updatePin($pin);
            UserRepository::getRepository()->save($user);

            UserActionHistoryService::logUpdatePin($u_idx);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            $logger = new StdoutLogger(__METHOD__);
            $logger->error($t->getMessage());

            throw $t;
        }

        ValidationTokenManager::invalidate(self::getUserKey($u_idx));
    }

    /**
     * @param int $u_idx
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function deletePin(int $u_idx): void
    {
        $user = self::getUser($u_idx);
        $user->deletePin();

        UserRepository::getRepository()->save($user);
    }

    /**
     * @param int $u_idx
     * @param string $pin
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnmatchedPinException
     * @throws PinEntryBlockedException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function validatePin(int $u_idx, string $pin): void
    {
        $user = self::getUser($u_idx);

        $policy = new PinEntryAbuseBlockPolicy();
        $abuse_blocker = new AbuseBlocker($policy, $u_idx);
        if ($abuse_blocker->isBlocked()) {
            $remaining_period_until_unblock = $abuse_blocker->getBlockedAt() + $policy->getBlockedPeriod() - time();
            throw new PinEntryBlockedException(
                $policy,
                ($remaining_period_until_unblock >= 0 ? $remaining_period_until_unblock : 0)
            );
        }

        if (!$user->isPinMatched($pin)) {
            throw new UnmatchedPinException($abuse_blocker->getRemainedTryCount());
        }
    }

    /**
     * @param int $u_idx
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws OnetouchPaySettingChangeDeclinedException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function enableOnetouchPay(int $u_idx): void
    {
        $user = self::getUser($u_idx);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            if (CardService::isCardRegistrationInProgress($u_idx)) {
                CardService::useRegisteredCard($u_idx);
            }

            $user->enableOnetouchPay();
            UserRepository::getRepository()->save($user);

            UserActionHistoryService::logEnableOnetouchPay($u_idx);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            $logger = new StdoutLogger(__METHOD__);
            $logger->error($t->getMessage());

            throw $t;
        }
    }

    /**
     * @param int $u_idx
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws OnetouchPaySettingChangeDeclinedException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function disableOnetouchPay(int $u_idx): void
    {
        $user = self::getUser($u_idx);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            if (CardService::isCardRegistrationInProgress($u_idx)) {
                CardService::useRegisteredCard($u_idx);
            }

            $user->disableOnetouchPay();
            UserRepository::getRepository()->save($user);

            UserActionHistoryService::logDisableOnetouchPay($u_idx);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            $logger = new StdoutLogger(__METHOD__);
            $logger->error($t->getMessage());

            throw $t;
        }
    }

    /**
     * @param int $u_idx
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function deleteOnetouchpay(int $u_idx): void
    {
        $user = self::getUser($u_idx);
        $user->deleteOnetouchPay();

        UserRepository::getRepository()->save($user);
    }

    /**
     * @param int $u_idx
     * @return null|bool
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function isUsingOnetouchPay(int $u_idx): ?bool
    {
        $user = self::getUser($u_idx);

        return $user->isUsingOnetouchPay();
    }

    /**
     * @param int $u_idx
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function validateUser(int $u_idx): void
    {
        self::getUser($u_idx);
    }

    /**
     * @param int $u_idx
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function createUser(int $u_idx): void
    {
        $user = new UserEntity($u_idx);
        UserRepository::getRepository()->save($user);
    }

    /**
     * @param int $u_idx
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function deleteUser(int $u_idx): void
    {
        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $user = self::getUser($u_idx);
            $user->leave();
            UserRepository::getRepository()->save($user);

            PaymentMethodAppService::deletePaymentMethods($u_idx);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }
    }

    /**
     * @param int $u_idx
     * @return UserEntity
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private static function getUser(int $u_idx): UserEntity
    {
        $user = UserRepository::getRepository()->findOneByUidx($u_idx);
        if (is_null($user)) {
            throw new NotFoundUserException();
        }

        if ($user->isLeaved()) {
            throw new LeavedUserException();
        }

        return $user;
    }

    /**
     * @param int $u_idx
     * @return string
     * @throws \Exception
     */
    public static function generateValidationToken(int $u_idx): string
    {
        $user_key = self::getUserKey($u_idx);

        return ValidationTokenManager::generate($user_key, 5 * TimeUnitConstant::SEC_IN_MINUTE);
    }

    /**
     * @param int $u_idx
     */
    public static function initializePinEntryHistory(int $u_idx): void
    {
        $policy = new PinEntryAbuseBlockPolicy();
        $abuse_blocker = new AbuseBlocker($policy, $u_idx);
        $abuse_blocker->initialize();
    }

    /**
     * @param int $u_idx
     * @param string $entered_validation_token
     * @throws UnauthorizedPinChangeException
     */
    private static function assertMatchedValidationToken(int $u_idx, string $entered_validation_token): void
    {
        $validation_token = ValidationTokenManager::get(self::getUserKey($u_idx));
        if ($validation_token !== $entered_validation_token) {
            throw new UnauthorizedPinChangeException();
        }
    }

    /**
     * @param int $u_idx
     * @return string
     */
    private static function getUserKey(int $u_idx): string
    {
        return "user:${u_idx}";
    }
}
