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
     * @throws \Exception
     */
    public static function logAddCard(UserEntity $user)
    {
        self::logUserAction($user, UserActionConstant::ADD_CARD);
    }

    /**
     * @param UserEntity $user
     * @throws \Exception
     */
    public static function logDeleteCard(UserEntity $user)
    {
        self::logUserAction($user, UserActionConstant::DELETE_CARD);
    }

    /**
     * @param UserEntity $user
     * @throws \Exception
     */
    public static function logUpdatePin(UserEntity $user)
    {
        self::logUserAction($user, UserActionConstant::UPDATE_PIN);
    }

    /**
     * @param UserEntity $user
     * @throws \Exception
     */
    public static function logEnableOnetouchPay(UserEntity $user)
    {
        self::logUserAction($user, UserActionConstant::ENABLE_ONETOUCH_PAY);
    }

    /**
     * @param UserEntity $user
     * @throws \Exception
     */
    public static function logDisableOnetouchPay(UserEntity $user)
    {
        self::logUserAction($user, UserActionConstant::DISABLE_ONETOUCH_PAY);
    }

    /**
     * @param UserEntity $user
     * @param string $action
     * @throws \Exception
     */
    private static function logUserAction(UserEntity $user, string $action)
    {
        $user_action_history = new UserActionHistoryEntity($user, $action);
        UserActionHistoryRepository::getRepository()->save($user_action_history);
    }
}
