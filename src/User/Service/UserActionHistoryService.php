<?php
declare(strict_types=1);

namespace RidiPay\User\Service;

use RidiPay\User\Constant\UserActionConstant;
use RidiPay\User\Entity\UserActionHistoryEntity;
use RidiPay\User\Entity\UserEntity;
use RidiPay\User\Repository\UserActionHistoryRepository;

class UserActionHistoryService
{
    /**
     * @param UserEntity $user
     */
    public static function logAddCard(UserEntity $user)
    {
        self::logUserAction($user, UserActionConstant::ADD_CARD);
    }

    /**
     * @param UserEntity $user
     */
    public static function logRemoveCard(UserEntity $user)
    {
        self::logUserAction($user, UserActionConstant::REMOVE_CARD);
    }

    /**
     * @param UserEntity $user
     * @param string $action
     */
    private static function logUserAction(UserEntity $user, string $action)
    {
        $user_action_history = new UserActionHistoryEntity($user, $action);
        UserActionHistoryRepository::getRepository()->save($user_action_history);
    }
}
