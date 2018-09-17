<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Service;

use RidiPay\User\Domain\Entity\UserEntity;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Repository\UserRepository;

class UserService
{
    /**
     * @param int $u_idx
     * @return UserEntity
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getActiveUser(int $u_idx): UserEntity
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
}
