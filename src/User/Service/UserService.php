<?php
declare(strict_types=1);

namespace RidiPay\User\Service;

use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Entity\UserEntity;
use RidiPay\User\Exception\NonUserException;
use RidiPay\User\Exception\LeavedUserException;
use RidiPay\User\Exception\UnmatchedPinException;
use RidiPay\User\Exception\WrongPinException;
use RidiPay\User\Repository\UserRepository;

class UserService
{
    /**
     * @param int $u_idx
     * @return null|UserEntity
     * @throws LeavedUserException
     * @throws NonUserException
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
     */
    public static function validatePin(int $u_idx, string $pin)
    {
        $user = self::getUser($u_idx);

        if (!$user->isPinMatched($pin)) {
            throw new UnmatchedPinException(); // TODO: 비밀번호 오입력 제한 -> 제한 도달 시 다른 Exception throw
        }
    }
}
