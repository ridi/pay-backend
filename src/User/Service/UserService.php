<?php
declare(strict_types=1);

namespace RidiPay\User\Service;

use RidiPay\User\Entity\UserEntity;
use RidiPay\User\Exception\NonUserException;
use RidiPay\User\Exception\LeavedUserException;
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
}
