<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Partner\Application\Service\PartnerAppService;
use RidiPay\Partner\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Transaction\Application\Dto\SubscriptionDto;
use RidiPay\Transaction\Application\Dto\SubscriptionPaymentDto;
use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;
use RidiPay\Transaction\Domain\Repository\SubscriptionRepository;
use RidiPay\User\Application\Service\PaymentMethodAppService;
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

        return new SubscriptionDto($payment_method_uuid, $subscription);
    }

    /**
     * @param string $partner_api_key
     * @param string $partner_secret_key
     * @param string $subscription_id
     * @param string $partner_transaction_id
     * @return SubscriptionPaymentDto
     * @throws UnauthorizedPartnerException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function paySubscription(
        string $partner_api_key,
        string $partner_secret_key,
        string $subscription_id,
        string $partner_transaction_id
    ) {
        $partner_id = PartnerAppService::validatePartner($partner_api_key, $partner_secret_key);

        $subscription = SubscriptionRepository::getRepository()->findOneByUuid(Uuid::fromString($subscription_id));
        if (is_null($subscription)) {
            throw new \Exception();
        }

        $payment_method_id = $subscription->getPaymentMethodId();
        $u_idx = PaymentMethodAppService::getUidxById($subscription->getPaymentMethodId());

        $approve_transaction_dto = TransactionAppService::approveTransactionBySubscription(
            $u_idx,
            $payment_method_id,
            $partner_id,
            $partner_transaction_id,
            $subscription->getProductName(),
            $subscription->getAmount(),
            $subscription->getSubscribedAt()
        );

        return new SubscriptionPaymentDto($approve_transaction_dto, $subscription);
    }
}
