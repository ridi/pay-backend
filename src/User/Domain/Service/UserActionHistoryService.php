<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Service;

use RidiPay\User\Domain\Entity\UserActionHistoryEntity;
use RidiPay\User\Domain\Entity\UserEntity;
use RidiPay\User\Domain\Repository\UserActionHistoryRepository;

class UserActionHistoryService
{
    private const ADD_CARD = 'ADD_CARD';
    private const DELETE_CARD = 'DELETE_CARD';
    private const UPDATE_PIN = 'UPDATE_PIN';
    private const ENABLE_ONETOUCH_PAY = 'ENABLE_ONETOUCH_PAY';
    private const DISABLE_ONETOUCH_PAY = 'DISABLE_ONETOUCH_PAY';

    /**
     * @param UserEntity $user
     * @throws \Exception
     */
    public static function logAddCard(UserEntity $user)
    {
        self::logUserAction($user, self::ADD_CARD);
    }

    /**
     * @param \RidiPay\User\Domain\Entity\UserEntity $user
     * @throws \Exception
     */
    public static function logDeleteCard(UserEntity $user)
    {
        self::logUserAction($user, self::DELETE_CARD);
    }

    /**
     * @param \RidiPay\User\Domain\Entity\UserEntity $user
     * @throws \Exception
     */
    public static function logUpdatePin(UserEntity $user)
    {
        self::logUserAction($user, self::UPDATE_PIN);
    }

    /**
     * @param UserEntity $user
     * @throws \Exception
     */
    public static function logEnableOnetouchPay(UserEntity $user)
    {
        self::logUserAction($user, self::ENABLE_ONETOUCH_PAY);
    }

    /**
     * @param \RidiPay\User\Domain\Entity\UserEntity $user
     * @throws \Exception
     */
    public static function logDisableOnetouchPay(UserEntity $user)
    {
        self::logUserAction($user, self::DISABLE_ONETOUCH_PAY);
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
