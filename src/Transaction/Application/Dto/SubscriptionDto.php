<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;

class SubscriptionDto
{
    /** @var string */
    public $subscription_id;

    /** @var int */
    public $u_idx;

    /** @var string */
    public $payment_method_id;

    /** @var string */
    public $payment_method_type;

    /** @var string */
    public $product_name;

    /** @var \DateTime */
    public $subscribed_at;

    /** @var \DateTime|null */
    public $unsubscribed_at;

    /**
     * @param SubscriptionEntity $subscription
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function __construct(SubscriptionEntity $subscription)
    {
        $this->subscription_id = $subscription->getUuid()->toString();

        $payment_method = PaymentMethodRepository::getRepository()->findOneById($subscription->getPaymentMethodId());
        $this->u_idx = $payment_method->getUidx();
        $this->payment_method_id = $payment_method->getUuid()->toString();
        $this->payment_method_type = $payment_method->getType();

        $this->product_name = $subscription->getProductName();
        $this->subscribed_at = $subscription->getSubscribedAt();
        $this->unsubscribed_at = $subscription->getUnsubscribedAt();
    }
}
