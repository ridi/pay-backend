<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Service;

use GuzzleHttp\Exception\ServerException;
use Predis\Client;
use Ramsey\Uuid\Uuid;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\Pg\Kcp\UnderMinimumPaymentAmountException;
use RidiPay\Library\SentryHelper;
use RidiPay\Library\TimeUnitConstant;
use RidiPay\Library\Validation\ApiSecret;
use RidiPay\Partner\Application\Service\PartnerAppService;
use RidiPay\Partner\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\Service\Buyer;
use RidiPay\Transaction\Application\Dto\SubscriptionRegistrationDto;
use RidiPay\Transaction\Application\Dto\SubscriptionDto;
use RidiPay\Transaction\Application\Dto\SubscriptionResumptionDto;
use RidiPay\Transaction\Application\Dto\SubscriptionPaymentDto;
use RidiPay\Transaction\Application\Dto\UnsubscriptionDto;
use RidiPay\Library\DuplicatedRequestException;
use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;
use RidiPay\Transaction\Domain\Entity\SubscriptionPaymentMethodHistoryEntity;
use RidiPay\Transaction\Domain\Exception\AlreadyCancelledSubscriptionException;
use RidiPay\Transaction\Domain\Exception\AlreadyResumedSubscriptionException;
use RidiPay\Transaction\Domain\Exception\NotFoundSubscriptionException;
use RidiPay\Transaction\Domain\Exception\NotReservedSubscriptionException;
use RidiPay\Transaction\Domain\Repository\SubscriptionPaymentMethodHistoryRepository;
use RidiPay\Transaction\Domain\Repository\SubscriptionRepository;
use RidiPay\Transaction\Domain\Service\BillingPaymentTransactionApprovalProcessor;
use RidiPay\Transaction\Domain\Service\BillingPaymentTransactionApprovalResult;
use RidiPay\Transaction\Domain\Service\RidiCashAutoChargeSubscriptionOptoutManager;
use RidiPay\Transaction\Domain\Service\RidiSelectSubscriptionOptoutManager;
use RidiPay\Transaction\Domain\SubscriptionConstant;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Domain\Exception\DeletedPaymentMethodException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;

class SubscriptionAppService
{
    /**
     * @param ApiSecret $partner_api_secret
     * @param string $payment_method_uuid
     * @param string $product_name
     * @param string $return_url
     * @return string
     * @throws DeletedPaymentMethodException
     * @throws UnauthorizedPartnerException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function reserveSubscription(
        ApiSecret $partner_api_secret,
        string $payment_method_uuid,
        string $product_name,
        string $return_url
    ): string {
        $partner_id = PartnerAppService::validatePartner(
            $partner_api_secret->getApiKey(),
            $partner_api_secret->getSecretKey()
        );
        $payment_method = PaymentMethodAppService::getPaymentMethodByUuid($payment_method_uuid);
        if ($payment_method->isDeleted()) {
            throw new DeletedPaymentMethodException();
        }

        $reservation_id = Uuid::uuid4()->toString();
        $reservation_key = self::getSubscriptionReservationKey($reservation_id);

        $redis = self::getRedisClient();
        $redis->hmset(
            $reservation_key,
            [
                'payment_method_id' => $payment_method->getId(),
                'partner_id' => $partner_id,
                'product_name' => $product_name,
                'return_url' => $return_url,
                'reserved_at' => (new \DateTime())->format(DATE_ATOM)
            ]
        );
        $redis->expire($reservation_key, TimeUnitConstant::SEC_IN_HOUR);

        return $reservation_id;
    }

    /**
     * @param string $reservation_id
     * @param int $u_idx
     * @return array
     * @throws DeletedPaymentMethodException
     * @throws NotReservedSubscriptionException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getReservedSubscription(string $reservation_id, int $u_idx): array
    {
        $subscription_reservation_key = self::getSubscriptionReservationKey($reservation_id);

        $redis = self::getRedisClient();
        $reserved_subscription = $redis->hgetall($subscription_reservation_key);
        if (empty($reserved_subscription)) {
            throw new NotReservedSubscriptionException();
        }

        $payment_method = PaymentMethodAppService::getPaymentMethod(intval($reserved_subscription['payment_method_id']));
        if ($payment_method->isDeleted()) {
            throw new DeletedPaymentMethodException();
        }
        if ($payment_method->getUidx() !== $u_idx) {
            throw new NotReservedSubscriptionException();
        }

        return $reserved_subscription;
    }

    /**
     * @param string $reservation_id
     * @param int $u_idx
     * @return SubscriptionRegistrationDto
     * @throws DeletedPaymentMethodException
     * @throws NotReservedSubscriptionException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function subscribe(
        string $reservation_id,
        int $u_idx
    ): SubscriptionRegistrationDto {
        $reserved_subscription = self::getReservedSubscription($reservation_id, $u_idx);

        $payment_method = PaymentMethodRepository::getRepository()->findOneById(
            intval($reserved_subscription['payment_method_id'])
        );
        if ($payment_method === null) {
            throw new UnregisteredPaymentMethodException();
        }
        if ($payment_method->isDeleted()) {
            throw new DeletedPaymentMethodException();
        }

        $em = EntityManagerProvider::getEntityManager();
        $subscription = $em->transactional(function () use ($payment_method, $reserved_subscription) {
            $subscription = new SubscriptionEntity(
                $payment_method->getId(),
                intval($reserved_subscription['partner_id']),
                $reserved_subscription['product_name']
            );
            SubscriptionRepository::getRepository()->save($subscription);

            SubscriptionPaymentMethodHistoryRepository::getRepository()->save(
                new SubscriptionPaymentMethodHistoryEntity($subscription, $payment_method)
            );

            return $subscription;
        });

        return new SubscriptionRegistrationDto($subscription, $reserved_subscription['return_url']);
    }

    /**
     * @param ApiSecret $partner_api_secret
     * @param string $subscription_uuid
     * @return UnsubscriptionDto
     * @throws AlreadyCancelledSubscriptionException
     * @throws NotFoundSubscriptionException
     * @throws UnauthorizedPartnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function unsubscribe(ApiSecret $partner_api_secret, string $subscription_uuid)
    {
        PartnerAppService::validatePartner($partner_api_secret->getApiKey(), $partner_api_secret->getSecretKey());

        $subscription_repo = SubscriptionRepository::getRepository();
        $subscription = $subscription_repo->findOneByUuid(Uuid::fromString($subscription_uuid));
        if (is_null($subscription)) {
            throw new NotFoundSubscriptionException();
        }

        $subscription->unsubscribe();
        $subscription_repo->save($subscription);

        return new UnsubscriptionDto($subscription);
    }

    /**
     * @param ApiSecret $partner_api_secret
     * @param string $subscription_uuid
     * @return SubscriptionResumptionDto
     * @throws AlreadyResumedSubscriptionException
     * @throws DeletedPaymentMethodException
     * @throws NotFoundSubscriptionException
     * @throws UnauthorizedPartnerException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function resumeSubscription(
        ApiSecret $partner_api_secret,
        string $subscription_uuid
    ): SubscriptionResumptionDto {
        PartnerAppService::validatePartner($partner_api_secret->getApiKey(), $partner_api_secret->getSecretKey());

        $subscription_repo = SubscriptionRepository::getRepository();
        $subscription = $subscription_repo->findOneByUuid(Uuid::fromString($subscription_uuid));
        if (is_null($subscription)) {
            throw new NotFoundSubscriptionException();
        }

        $payment_method = PaymentMethodAppService::getPaymentMethod($subscription->getPaymentMethodId());
        if ($payment_method->isDeleted()) {
            throw new DeletedPaymentMethodException();
        }

        $subscription->resumeSubscription();
        $subscription_repo->save($subscription);

        return new SubscriptionResumptionDto($subscription);
    }

    /**
     * @param ApiSecret $partner_api_secret
     * @param string $subscription_uuid
     * @param string $partner_transaction_id
     * @param int $amount
     * @param string $buyer_id
     * @param string $buyer_name
     * @param string $buyer_email
     * @param string $invoice_id
     * @return SubscriptionPaymentDto
     * @throws DuplicatedRequestException
     * @throws DeletedPaymentMethodException
     * @throws NotFoundSubscriptionException
     * @throws TransactionApprovalException
     * @throws UnauthorizedPartnerException
     * @throws UnderMinimumPaymentAmountException
     * @throws UnregisteredPaymentMethodException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function paySubscription(
        ApiSecret $partner_api_secret,
        string $subscription_uuid,
        string $partner_transaction_id,
        int $amount,
        string $buyer_id,
        string $buyer_name,
        string $buyer_email,
        string $invoice_id
    ): SubscriptionPaymentDto {
        $partner_id = PartnerAppService::validatePartner(
            $partner_api_secret->getApiKey(),
            $partner_api_secret->getSecretKey()
        );

        $billing_payment_transaction_approval_processor = new BillingPaymentTransactionApprovalProcessor(
            $subscription_uuid,
            $partner_id,
            $partner_transaction_id,
            $amount,
            new Buyer($buyer_id, $buyer_name, $buyer_email),
            $invoice_id
        );
        /** @var BillingPaymentTransactionApprovalResult $billing_payment_transaction_approval_result */
        $billing_payment_transaction_approval_result = $billing_payment_transaction_approval_processor->process();

        return new SubscriptionPaymentDto($billing_payment_transaction_approval_result);
    }

    /**
     * @param ApiSecret $partner_api_secret
     * @param string $subscription_uuid
     * @return SubscriptionDto
     * @throws NotFoundSubscriptionException
     * @throws UnauthorizedPartnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getSubscription(ApiSecret $partner_api_secret, string $subscription_uuid): SubscriptionDto
    {
        $partner_id = PartnerAppService::validatePartner(
            $partner_api_secret->getApiKey(),
            $partner_api_secret->getSecretKey()
        );

        $subscription = SubscriptionRepository::getRepository()->findOneByUuid(Uuid::fromString($subscription_uuid));
        if ($subscription === null) {
            throw new NotFoundSubscriptionException();
        }
        if ($partner_id !== $subscription->getPartnerId()) {
            throw new UnauthorizedPartnerException();
        }

        return new SubscriptionDto($subscription);
    }

    /**
     * @param int $payment_method_id
     * @return string[]
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getSubscriptionByPaymentMethodId(int $payment_method_id)
    {
        $subscriptions = SubscriptionRepository::getRepository()->findActiveOnesByPaymentMethodId($payment_method_id);

        return array_map(
            function (SubscriptionEntity $subscription) {
                return new SubscriptionDto($subscription);
            },
            $subscriptions
        );
    }

    /**
     * @param int $u_idx
     * @param int $payment_method_id
     * @throws AlreadyCancelledSubscriptionException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function optoutSubscriptions(int $u_idx, int $payment_method_id): void
    {
        $subscription_repo = SubscriptionRepository::getRepository();
        $subscriptions = $subscription_repo->findActiveOnesByPaymentMethodId($payment_method_id);

        $first_party_subscriptions = [];

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            foreach ($subscriptions as $subscription) {
                if (self::isFirstPartySubscription($subscription)) {
                    $first_party_subscriptions[] = $subscription;
                }

                $subscription->unsubscribe();
                $subscription_repo->save($subscription);
            }

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }

        foreach ($first_party_subscriptions as $subscription) {
            try {
                self::optoutFirstPartySubscription($u_idx, $subscription);
            } catch (\Throwable $t) {
                // First-party 구독 해지 요청 중 발생한 오류가 RIDI Pay의 구독 해지 과정에 영향을 주지 않도록 catch
                if ($t instanceof ServerException) {
                    SentryHelper::captureException($t);
                }
            }
        }
    }

    /**
     * @param int $previous_payment_method_id
     * @param int $new_payment_method_id
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function changePaymentMethod(int $previous_payment_method_id, int $new_payment_method_id): void
    {
        $new_payment_method = PaymentMethodRepository::getRepository()->findOneById($new_payment_method_id);
        if ($new_payment_method === null) {
            throw new UnregisteredPaymentMethodException();
        }

        $subscriptions = SubscriptionRepository::getRepository()->findByPaymentMethodId($previous_payment_method_id);

        $em = EntityManagerProvider::getEntityManager();
        $em->transactional(function () use ($new_payment_method, $subscriptions) {
            foreach ($subscriptions as $subscription) {
                $subscription->setPaymentMethodId($new_payment_method->getId());
                SubscriptionRepository::getRepository()->save($subscription);

                // 결제 수단 변경 이력 기록
                SubscriptionPaymentMethodHistoryRepository::getRepository()->save(
                    new SubscriptionPaymentMethodHistoryEntity($subscription, $new_payment_method)
                );
            }
        });
    }

    /**
     * @param SubscriptionEntity $subscription
     * @return bool
     */
    private static function isFirstPartySubscription(SubscriptionEntity $subscription): bool
    {
        return in_array(
            $subscription->getProductName(),
            [
                SubscriptionConstant::PRODUCT_RIDI_CASH_AUTO_CHARGE,
                SubscriptionConstant::PRODUCT_RIDISELECT
            ],
            true
        );
    }

    /**
     * @param int $u_idx
     * @param SubscriptionEntity $subscription
     * @throws \Exception
     */
    private static function optoutFirstPartySubscription(int $u_idx, SubscriptionEntity $subscription)
    {
        if ($subscription->getProductName() === SubscriptionConstant::PRODUCT_RIDI_CASH_AUTO_CHARGE) {
            RidiCashAutoChargeSubscriptionOptoutManager::optout(
                $u_idx,
                $subscription->getUuid()->toString()
            );
        } elseif ($subscription->getProductName() === SubscriptionConstant::PRODUCT_RIDISELECT) {
            RidiSelectSubscriptionOptoutManager::optout(
                $u_idx,
                $subscription->getUuid()->toString()
            );
        }
    }

    /**
     * @param string $reservation_id
     * @return string
     */
    private static function getSubscriptionReservationKey(string $reservation_id): string
    {
        return "subscription:reservation:${reservation_id}";
    }

    /**
     * @return Client
     */
    private static function getRedisClient(): Client
    {
        return new Client(['host' => getenv('REDIS_HOST', true)]);
    }
}
