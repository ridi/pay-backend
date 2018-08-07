<?php
declare(strict_types=1);

namespace RidiPay\User\Service;

use RidiPay\User\Entity\UserEntity;
use RidiPay\User\Repository\UserRepository;

class UserService
{
    /**
     * @param int $u_idx
     * @return bool
     */
    public static function isUser(int $u_idx): bool
    {
        $user = UserRepository::getRepository()->findOneByUidx($u_idx);

        return !is_null($user);
    }

    /**
     * @param int $u_idx
     */
    public static function createUser(int $u_idx): void
    {
        $user = new UserEntity($u_idx);

        UserRepository::getRepository()->save($user);
    }
}
