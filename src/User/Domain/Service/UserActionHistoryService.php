<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Service;

use RidiPay\User\Domain\Entity\UserActionHistoryEntity;
use RidiPay\User\Domain\Repository\UserActionHistoryRepository;
use RidiPay\User\Domain\Repository\UserRepository;
use RidiPay\User\Domain\UserActionHistoryConstant;

class UserActionHistoryService
{
    /**
     * @param int $u_idx
     * @throws \Exception
     */
    public static function logRegisterCard(int $u_idx): void
    {
        self::logUserAction($u_idx, UserActionHistoryConstant::ACTION_REGISTER_CARD);
    }

    /**
     * @param int $u_idx
     * @throws \Exception
     */
    public static function logDeleteCard(int $u_idx): void
    {
        self::logUserAction($u_idx, UserActionHistoryConstant::ACTION_DELETE_CARD);
    }

    /**
     * @param int $u_idx
     * @throws \Exception
     */
    public static function logUpdatePin(int $u_idx): void
    {
        self::logUserAction($u_idx, UserActionHistoryConstant::ACTION_UPDATE_PIN);
    }

    /**
     * @param int $u_idx
     * @param string $action
     * @throws \Exception
     */
    private static function logUserAction(int $u_idx, string $action): void
    {
        $user = UserRepository::getRepository()->findOneByUidx($u_idx);
        $user_action_history = new UserActionHistoryEntity($user, $action);
        UserActionHistoryRepository::getRepository()->save($user_action_history);
    }
}
