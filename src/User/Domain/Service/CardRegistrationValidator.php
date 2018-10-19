<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Service;

use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Exception\CardAlreadyExistsException;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;

class CardRegistrationValidator
{
    /** @var int */
    private $u_idx;

    /**
     * @param int $u_idx
     */
    public function __construct(int $u_idx)
    {
        $this->u_idx = $u_idx;
    }

    /**
     * @throws CardAlreadyExistsException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function validate()
    {
        $this->assertNotHavingCard();
    }

    /**
     * @throws CardAlreadyExistsException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private function assertNotHavingCard(): void
    {
        $available_payment_methods = PaymentMethodRepository::getRepository()->getAvailablePaymentMethods(
            $this->u_idx
        );
        $available_cards = array_filter(
            $available_payment_methods,
            function (PaymentMethodEntity $payment_method) {
                return $payment_method->isCard();
            }
        );

        if (!empty($available_cards)) {
            throw new CardAlreadyExistsException();
        }
    }
}
