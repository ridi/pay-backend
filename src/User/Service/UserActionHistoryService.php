<?php
declare(strict_types=1);

namespace RidiPay\User\Service;

use RidiPay\User\Constant\UserActionConstant;
use RidiPay\User\Entity\UserActionHistoryEntity;
use RidiPay\User\Repository\UserActionHistoryRepository;

class UserActionHistoryService
{
    /**
     * @param int $u_idx
     */
    public static function logAddCard(int $u_idx)
    {
        self::logUserAction($u_idx, UserActionConstant::ADD_CARD);
    }

    /**
     * @param int $u_idx
     */
    public static function logRemoveCard(int $u_idx)
    {
        self::logUserAction($u_idx, UserActionConstant::REMOVE_CARD);
    }

    /**
     * @param int $u_idx
     * @param string $action
     */
    private static function logUserAction(int $u_idx, string $action)
    {
        $user_action_history = new UserActionHistoryEntity($u_idx, $action);
        UserActionHistoryRepository::getRepository()->save($user_action_history);
    }
}
