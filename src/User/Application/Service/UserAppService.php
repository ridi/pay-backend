<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use Predis\Client;
use Ridibooks\OAuth2\Symfony\Provider\User;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\MailRenderer;
use RidiPay\Library\TimeUnitConstant;
use RidiPay\Library\ValidationTokenManager;
use RidiPay\User\Application\Dto\PinUpdateHistoryItemDto;
use RidiPay\User\Domain\Entity\UserActionHistoryEntity;
use RidiPay\User\Domain\Entity\UserEntity;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\PinEntryBlockedException;
use RidiPay\User\Domain\Exception\UnchangedPinException;
use RidiPay\User\Domain\Exception\UnmatchedPinException;
use RidiPay\User\Domain\Exception\WrongFormattedPinException;
use RidiPay\User\Domain\Repository\UserActionHistoryRepository;
use RidiPay\User\Domain\Repository\UserRepository;
use RidiPay\User\Domain\Service\AbuseBlocker;
use RidiPay\User\Domain\Service\PinEntryAbuseBlockPolicy;
use RidiPay\User\Domain\Service\UserActionHistoryService;
use RidiPay\User\Domain\UserActionHistoryConstant;

class UserAppService
{
    /**
     * @param int $u_idx
     * @param string $pin
     * @throws WrongFormattedPinException
     */
    public static function createPin(int $u_idx, string $pin): void
    {
        $user_key = self::getUserKey($u_idx);

        $redis = self::getRedisClient();
        $redis->hset($user_key, 'pin', UserEntity::createPin($pin));
        $redis->expire($user_key, TimeUnitConstant::SEC_IN_HOUR);
    }

    /**
     * @param int $u_idx
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function useCreatedPin(int $u_idx): void
    {
        $user_key = self::getUserKey($u_idx);
        $field_name = 'pin';

        $redis = self::getRedisClient();
        $pin = $redis->hget($user_key, $field_name);

        $user = self::getUser($u_idx);
        $user->setPin($pin);
        UserRepository::getRepository()->save($user);

        $redis->hdel($user_key, [$field_name]);

        UserAppService::generateValidationToken($u_idx);
    }

    /**
     * @param User $oauth2_user
     * @param string $pin
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnchangedPinException
     * @throws WrongFormattedPinException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function updatePin(User $oauth2_user, string $pin): void
    {
        $u_idx = $oauth2_user->getUidx();
        $user = self::getUser($u_idx);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $user->updatePin($pin);
            UserRepository::getRepository()->save($user);

            UserActionHistoryService::logUpdatePin($u_idx);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }

        $data = ['u_id' => $oauth2_user->getUid()];
        $email_body = (new MailRenderer())->render('pin_change_alert.twig', $data);
        EmailSender::send(
            $oauth2_user->getEmail(),
            "{$oauth2_user->getUid()}님, 결제 비밀번호 변경 안내드립니다.",
            $email_body
        );
    }

    /**
     * @param int $u_idx
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function deletePin(int $u_idx): void
    {
        $user = self::getUser($u_idx);
        $user->deletePin();

        UserRepository::getRepository()->save($user);
    }

    /**
     * @param int $u_idx
     * @param string $pin
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnmatchedPinException
     * @throws PinEntryBlockedException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function validatePin(int $u_idx, string $pin): void
    {
        $user = self::getUser($u_idx);
        $is_pin_matched = $user->isPinMatched($pin);

        $policy = new PinEntryAbuseBlockPolicy();
        $abuse_blocker = new AbuseBlocker($policy, $u_idx);

        if (!$is_pin_matched) {
            $abuse_blocker->increaseErrorCount();
        }

        if ($abuse_blocker->isBlocked()) {
            $remaining_period_until_unblock = $abuse_blocker->getBlockedAt() + $policy->getBlockedPeriod() - time();
            throw new PinEntryBlockedException(
                $policy,
                ($remaining_period_until_unblock >= 0 ? $remaining_period_until_unblock : 0)
            );
        }

        if (!$is_pin_matched) {
            throw new UnmatchedPinException($abuse_blocker->getRemainedTryCount());
        }

        $abuse_blocker->initialize();
    }

    /**
     * @param int $u_idx
     * @return bool
     */
    public static function isPinValidationRequired(int $u_idx): bool
    {
        return ValidationTokenManager::get(self::getUserKey($u_idx)) === null;
    }

    /**
     * @param int $u_idx
     * @return PinUpdateHistoryItemDto[]
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getPinUpdateHistory(int $u_idx): array
    {
        $actions = UserActionHistoryRepository::getRepository()->findByUidxAndActions(
            $u_idx,
            [UserActionHistoryConstant::ACTION_UPDATE_PIN]
        );

        return array_map(
            function (UserActionHistoryEntity $action) {
                return new PinUpdateHistoryItemDto($action);
            },
            $actions
        );
    }

    /**
     * @param int $u_idx
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function deleteOnetouchPay(int $u_idx): void
    {
        $user = self::getUser($u_idx);
        $user->deleteOnetouchPay();

        UserRepository::getRepository()->save($user);
    }

    /**
     * @param int $u_idx
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function validateUser(int $u_idx): void
    {
        self::getUser($u_idx);
    }

    /**
     * @param int $u_idx
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function createUser(int $u_idx): void
    {
        $user = new UserEntity($u_idx);
        UserRepository::getRepository()->save($user);
    }

    /**
     * @param int $u_idx
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function deleteUser(int $u_idx): void
    {
        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $user = self::getUser($u_idx);
            $user->leave();
            UserRepository::getRepository()->save($user);

            PaymentMethodAppService::deleteAllPaymentMethods($u_idx);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }
    }

    /**
     * @param int $u_idx
     * @return string
     * @throws \Exception
     */
    public static function generateValidationToken(int $u_idx): string
    {
        $user_key = self::getUserKey($u_idx);

        return ValidationTokenManager::generate($user_key, 5 * TimeUnitConstant::SEC_IN_MINUTE);
    }

    /**
     * @param int $u_idx
     */
    public static function initializePinEntryHistory(int $u_idx): void
    {
        $policy = new PinEntryAbuseBlockPolicy();
        $abuse_blocker = new AbuseBlocker($policy, $u_idx);
        $abuse_blocker->initialize();
    }

    /**
     * @param int $u_idx
     * @return UserEntity
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getUser(int $u_idx): UserEntity
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

    /**
     * @return Client
     */
    private static function getRedisClient(): Client
    {
        return new Client(['host' => getenv('REDIS_HOST', true)]);
    }

    /**
     * @param int $u_idx
     * @return string
     */
    public static function getUserKey(int $u_idx): string
    {
        return "user:${u_idx}";
    }
}
