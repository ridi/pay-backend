<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\Log\StdoutLogger;
use RidiPay\User\Application\Dto\UserInformationDto;
use RidiPay\User\Domain\Entity\UserEntity;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\OnetouchPaySettingChangeDeclinedException;
use RidiPay\User\Domain\Exception\PasswordEntryBlockedException;
use RidiPay\User\Domain\Exception\UnmatchedPasswordException;
use RidiPay\User\Domain\Exception\UnmatchedPinException;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;
use RidiPay\User\Domain\Exception\WrongFormattedPinException;
use RidiPay\User\Domain\Repository\UserRepository;
use RidiPay\User\Domain\Service\AbuseBlocker;
use RidiPay\User\Domain\Service\PasswordEntryAbuseBlockPolicy;
use RidiPay\User\Domain\Service\PinEntryAbuseBlockPolicy;
use RidiPay\User\Domain\Service\UserActionHistoryService;
use RidiPay\User\Domain\Service\UserService;

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
        $user = UserService::getActiveUser($u_idx);
        $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($u_idx);

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
    public static function updatePin(int $u_idx, string $pin): void
    {
        $user = UserService::getActiveUser($u_idx);

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
    }

    /**
     * @param int $u_idx
     * @param string $pin
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnmatchedPinException
     * @throws PasswordEntryBlockedException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function validatePin(int $u_idx, string $pin): void
    {
        $user = UserService::getActiveUser($u_idx);

        if (!$user->isPinMatched($pin)) {
            $policy = new PinEntryAbuseBlockPolicy();
            $abuse_blocker = new AbuseBlocker($policy, $u_idx);

            if (!$abuse_blocker->isBlocked()) {
                throw new UnmatchedPinException();
            }

            $remaining_period_until_unblock = $abuse_blocker->getBlockedAt() + $policy->getBlockedPeriod() - time();
            throw new PasswordEntryBlockedException(
                $policy,
                ($remaining_period_until_unblock >= 0 ? $remaining_period_until_unblock : 0)
            );
        }
    }

    /**
     * @param int $u_idx
     * @param string $u_id
     * @param string $password
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws PasswordEntryBlockedException
     * @throws UnmatchedPasswordException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function validatePassword(int $u_idx, string $u_id, string $password): void
    {
        $user = UserService::getActiveUser($u_idx);

        if (!$user->isPasswordMatched($u_id, $password)) {
            $policy = new PasswordEntryAbuseBlockPolicy();
            $abuse_blocker = new AbuseBlocker($policy, $u_idx);

            if (!$abuse_blocker->isBlocked()) {
                throw new UnmatchedPasswordException();
            }

            $remaining_period_until_unblock = $abuse_blocker->getBlockedAt() + $policy->getBlockedPeriod() - time();
            throw new PasswordEntryBlockedException(
                $policy,
                ($remaining_period_until_unblock >= 0 ? $remaining_period_until_unblock : 0)
            );
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
        $user = UserService::getActiveUser($u_idx);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
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
        $user = UserService::getActiveUser($u_idx);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
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
     * @return bool
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function isUsingOnetouchPay(int $u_idx): bool
    {
        $user = UserService::getActiveUser($u_idx);

        return $user->isUsingOnetouchPay();
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
}
