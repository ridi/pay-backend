<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\Pg\Kcp\UnderMinimumPaymentAmountException;
use RidiPay\Partner\Application\Service\PartnerAppService;
use RidiPay\Partner\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Transaction\Application\Dto\SubscriptionDto;
use RidiPay\Transaction\Application\Dto\SubscriptionResumptionDto;
use RidiPay\Transaction\Application\Dto\SubscriptionPaymentDto;
use RidiPay\Transaction\Application\Dto\UnsubscriptionDto;
use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;
use RidiPay\Transaction\Domain\Exception\AlreadyCancelledSubscriptionException;
use RidiPay\Transaction\Domain\Exception\AlreadyResumedSubscriptionException;
use RidiPay\Transaction\Domain\Exception\NotFoundSubscriptionException;
use RidiPay\Transaction\Domain\Repository\SubscriptionRepository;
use RidiPay\Transaction\Domain\Service\RidiCashAutoChargeSubscriptionOptoutManager;
use RidiPay\Transaction\Domain\Service\RidiSelectSubscriptionOptoutManager;
use RidiPay\Transaction\Domain\SubscriptionConstant;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Domain\Exception\DeletedPaymentMethodException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;

class SubscriptionAppService
{
    /**
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param string $payment_method_uuid
     * @param string $product_name
     * @return SubscriptionDto
     * @throws DeletedPaymentMethodException
     * @throws UnauthorizedPartnerException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function subscribe(
        string $partner_api_key,
        string $partner_secret_key,
        string $payment_method_uuid,
        string $product_name
    ): SubscriptionDto {
        $partner_id = PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);
        $payment_method_id = PaymentMethodAppService::getPaymentMethodIdByUuid($payment_method_uuid);

        $subscription = new SubscriptionEntity($payment_method_id, $partner_id, $product_name);
        SubscriptionRepository::getRepository()->save($subscription);

        return new SubscriptionDto($subscription);
    }

    /**
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param string $subscription_uuid
     * @return UnsubscriptionDto
     * @throws AlreadyCancelledSubscriptionException
     * @throws NotFoundSubscriptionException
     * @throws UnauthorizedPartnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function unsubscribe(
        string $partner_api_key,
        string $partner_secret_key,
        string $subscription_uuid
    ) {
        PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);

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
     * @param string $partner_api_key
     * @param string $partner_secret_key
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
        string $partner_api_key,
        string $partner_secret_key,
        string $subscription_uuid
    ): SubscriptionResumptionDto {
        PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);

        $subscription_repo = SubscriptionRepository::getRepository();
        $subscription = $subscription_repo->findOneByUuid(Uuid::fromString($subscription_uuid));
        if (is_null($subscription)) {
            throw new NotFoundSubscriptionException();
        }

        PaymentMethodAppService::validatePaymentMethod($subscription->getPaymentMethodId());

        $subscription->resumeSubscription();
        $subscription_repo->save($subscription);

        return new SubscriptionResumptionDto($subscription);
    }

    /**
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param string $subscription_uuid
     * @param string $partner_transaction_id
     * @param int $amount
     * @param string $buyer_id
     * @param string $buyer_name
     * @param string $buyer_email
     * @return SubscriptionPaymentDto
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
        string $partner_api_key,
        string $partner_secret_key,
        string $subscription_uuid,
        string $partner_transaction_id,
        int $amount,
        string $buyer_id,
        string $buyer_name,
        string $buyer_email
    ) {
        $partner_id = PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);

        $subscription = SubscriptionRepository::getRepository()->findOneByUuid(Uuid::fromString($subscription_uuid));
        if (is_null($subscription) || $subscription->isUnsubscribed()) {
            throw new NotFoundSubscriptionException();
        }

        $payment_method_id = $subscription->getPaymentMethodId();
        $u_idx = PaymentMethodAppService::getUidxById($payment_method_id);

        $approve_transaction_dto = TransactionAppService::approveTransactionBySubscription(
            $u_idx,
            $payment_method_id,
            $partner_id,
            $partner_transaction_id,
            $subscription->getId(),
            $subscription->getProductName(),
            $amount,
            $subscription->getSubscribedAt(),
            $buyer_id,
            $buyer_name,
            $buyer_email
        );

        return new SubscriptionPaymentDto($approve_transaction_dto, $subscription);
    }

    /**
     * @param int $payment_method_id
     * @return string[]
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getSubscriptions(int $payment_method_id)
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
            self::optoutFirstPartySubscription($u_idx, $subscription);
        }
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
            ]
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
}
