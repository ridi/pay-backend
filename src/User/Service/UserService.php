<?php
declare(strict_types=1);

namespace RidiPay\User\Service;

use RidiPay\User\Entity\UserEntity;
use RidiPay\User\Repository\UserRepository;

class UserService
{
    /**
     * @param int $u_idx
     * @return null|UserEntity
     */
    public static function getUser(int $u_idx): ?UserEntity
    {
        return UserRepository::getRepository()->findOneByUidx($u_idx);
    }

    /**
     * @param int $u_idx
     * @return UserEntity
     */
    public static function createUser(int $u_idx): UserEntity
    {
        $user = new UserEntity($u_idx);
        UserRepository::getRepository()->save($user);

        return $user;
    }
}
