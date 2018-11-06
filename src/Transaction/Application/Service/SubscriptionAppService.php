<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Partner\Application\Service\PartnerAppService;
use RidiPay\Partner\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Transaction\Application\Dto\SubscriptionDto;
use RidiPay\Transaction\Application\Dto\SubscriptionResumptionDto;
use RidiPay\Transaction\Application\Dto\SubscriptionPaymentDto;
use RidiPay\Transaction\Application\Dto\UnsubscriptionDto;
use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;
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
     * @param int $amount
     * @return SubscriptionDto
     * @throws UnauthorizedPartnerException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function subscribe(
        string $partner_api_key,
        string $partner_secret_key,
        string $payment_method_uuid,
        string $product_name,
        int $amount
    ): SubscriptionDto {
        $partner_id = PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);
        $payment_method_id = PaymentMethodAppService::getPaymentMethodIdByUuid($payment_method_uuid);

        $subscription = new SubscriptionEntity($payment_method_id, $partner_id, $product_name, $amount);
        SubscriptionRepository::getRepository()->save($subscription);

        return new SubscriptionDto($subscription);
    }

    /**
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param string $subscription_uuid
     * @return UnsubscriptionDto
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
        if (is_null($subscription) || $subscription->isUnsubscribed()) {
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
     * @return SubscriptionPaymentDto
     * @throws NotFoundSubscriptionException
     * @throws TransactionApprovalException
     * @throws UnauthorizedPartnerException
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
        string $partner_transaction_id
    ) {
        $partner_id = PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);

        $subscription = SubscriptionRepository::getRepository()->findOneByUuid(Uuid::fromString($subscription_uuid));
        if (is_null($subscription) || $subscription->isUnsubscribed()) {
            throw new NotFoundSubscriptionException();
        }

        $payment_method_id = $subscription->getPaymentMethodId();
        $u_idx = PaymentMethodAppService::getUidxById($subscription->getPaymentMethodId());

        $approve_transaction_dto = TransactionAppService::approveTransactionBySubscription(
            $u_idx,
            $payment_method_id,
            $partner_id,
            $partner_transaction_id,
            $subscription->getId(),
            $subscription->getProductName(),
            $subscription->getAmount(),
            $subscription->getSubscribedAt()
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
        $subscriptions = SubscriptionRepository::getRepository()->findByPaymentMethodId($payment_method_id);

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
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function optoutFirstPartySubscriptions(int $u_idx, int $payment_method_id): void
    {
        $subscriptions = SubscriptionRepository::getRepository()->findByPaymentMethodId($payment_method_id);
        foreach ($subscriptions as $subscription) {
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
}
