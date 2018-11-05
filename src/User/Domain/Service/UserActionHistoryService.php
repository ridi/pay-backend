<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Service;

use RidiPay\User\Domain\Entity\UserActionHistoryEntity;
use RidiPay\User\Domain\Repository\UserActionHistoryRepository;
use RidiPay\User\Domain\Repository\UserRepository;

class UserActionHistoryService
{
    private const REGISTER_CARD = 'REGISTER_CARD';
    private const DELETE_CARD = 'DELETE_CARD';
    private const UPDATE_PIN = 'UPDATE_PIN';
    private const ENABLE_ONETOUCH_PAY = 'ENABLE_ONETOUCH_PAY';
    private const DISABLE_ONETOUCH_PAY = 'DISABLE_ONETOUCH_PAY';

    /**
     * @param int $u_idx
     * @throws \Exception
     */
    public static function logRegisterCard(int $u_idx): void
    {
        self::logUserAction($u_idx, self::REGISTER_CARD);
    }

    /**
     * @param int $u_idx
     * @throws \Exception
     */
    public static function logDeleteCard(int $u_idx): void
    {
        self::logUserAction($u_idx, self::DELETE_CARD);
    }

    /**
     * @param int $u_idx
     * @throws \Exception
     */
    public static function logUpdatePin(int $u_idx): void
    {
        self::logUserAction($u_idx, self::UPDATE_PIN);
    }

    /**
     * @param int $u_idx
     * @throws \Exception
     */
    public static function logEnableOnetouchPay(int $u_idx): void
    {
        self::logUserAction($u_idx, self::ENABLE_ONETOUCH_PAY);
    }

    /**
     * @param int $u_idx
     * @throws \Exception
     */
    public static function logDisableOnetouchPay(int $u_idx): void
    {
        self::logUserAction($u_idx, self::DISABLE_ONETOUCH_PAY);
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
