<?php
declare(strict_types=1);

namespace RidiPay\User\Service;

use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Entity\UserEntity;
use RidiPay\User\Exception\PasswordEntryBlockedException;
use RidiPay\User\Exception\NonUserException;
use RidiPay\User\Exception\LeavedUserException;
use RidiPay\User\Exception\OnetouchPaySettingException;
use RidiPay\User\Exception\UnmatchedPasswordException;
use RidiPay\User\Exception\UnmatchedPinException;
use RidiPay\User\Exception\WrongPinException;
use RidiPay\User\Model\AbuseBlocker;
use RidiPay\User\Model\PasswordEntryAbuseBlockPolicy;
use RidiPay\User\Model\PinEntryAbuseBlockPolicy;
use RidiPay\User\Repository\UserRepository;

class UserService
{
    /**
     * @param int $u_idx
     * @return null|UserEntity
     * @throws LeavedUserException
     * @throws NonUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function getUser(int $u_idx): ?UserEntity
    {
        $user = UserRepository::getRepository()->findOneByUidx($u_idx);
        if (is_null($user)) {
            throw new NonUserException();
        }

        if ($user->isLeaved()) {
            throw new LeavedUserException();
        }

        return $user;
    }

    /**
     * @param int $u_idx
     * @return UserEntity
     * @throws LeavedUserException
     * @throws \Exception
     */
    public static function createUserIfNotExists(int $u_idx): UserEntity
    {
        try {
            $user = self::getUser($u_idx);
        } catch (NonUserException $e) {
            $user = self::createUser($u_idx);
        }

        return $user;
    }

    /**
     * @param int $u_idx
     * @return UserEntity
     * @throws \Exception
     */
    private static function createUser(int $u_idx): UserEntity
    {
        $user = new UserEntity($u_idx);
        UserRepository::getRepository()->save($user);

        return $user;
    }

    /**
     * @param int $u_idx
     * @param string $pin
     * @throws LeavedUserException
     * @throws NonUserException
     * @throws WrongPinException
     * @throws \Throwable
     */
    public static function updatePin(int $u_idx, string $pin)
    {
        $user = self::getUser($u_idx);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $user->updatePin($pin);
            UserRepository::getRepository()->save($user);

            UserActionHistoryService::logUpdatePin($user);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }
    }

    /**
     * @param int $u_idx
     * @param string $pin
     * @throws LeavedUserException
     * @throws NonUserException
     * @throws UnmatchedPinException
     * @throws PasswordEntryBlockedException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function validatePin(int $u_idx, string $pin)
    {
        $user = self::getUser($u_idx);

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
     * @param string $pin
     * @throws LeavedUserException
     * @throws NonUserException
     * @throws PasswordEntryBlockedException
     * @throws UnmatchedPasswordException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function validatePassword(int $u_idx, string $pin)
    {
        $user = self::getUser($u_idx);

        if (!$user->isPasswordMatched($pin)) {
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
     * @throws NonUserException
     * @throws OnetouchPaySettingException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     * @throws \Throwable
     */
    public static function enableOnetouchPay(int $u_idx)
    {
        $user = self::getUser($u_idx);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $user->enableOnetouchPay();
            UserRepository::getRepository()->save($user);

            UserActionHistoryService::logEnableOnetouchPay($user);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }
    }

    /**
     * @param int $u_idx
     * @throws LeavedUserException
     * @throws NonUserException
     * @throws OnetouchPaySettingException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     * @throws \Throwable
     */
    public static function disableOnetouchPay(int $u_idx)
    {
        $user = self::getUser($u_idx);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $user->disableOnetouchPay();
            UserRepository::getRepository()->save($user);

            UserActionHistoryService::logDisableOnetouchPay($user);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }
    }

    /**
     * @param int $u_idx
     * @return bool
     * @throws LeavedUserException
     * @throws NonUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function isUsingOnetouchPay(int $u_idx): bool
    {
        $user = self::getUser($u_idx);

        return $user->isUsingOnetouchPay();
    }
}
