<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Repository;

use Ramsey\Uuid\UuidInterface;
use RidiPay\Library\BaseEntityRepository;
use RidiPay\User\Domain\Entity\CardEntity;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;

class PaymentMethodRepository extends BaseEntityRepository
{
    /**
     * @param int $id
     * @return PaymentMethodEntity|null
     */
    public function findOneById(int $id): ?PaymentMethodEntity
    {
        return $this->findOneBy(['id' => $id]);
    }

    /**
     * @param UuidInterface $uuid
     * @return PaymentMethodEntity|null
     */
    public function findOneByUuid(UuidInterface $uuid): ?PaymentMethodEntity
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    /**
     * @param int $u_idx
     * @return PaymentMethodEntity[]
     */
    public function getAvailablePaymentMethods(int $u_idx): array
    {
        $payment_methods = $this->findBy(['u_idx' => $u_idx, 'deleted_at' => null]);
        return array_filter(
            $payment_methods,
            function (PaymentMethodEntity $payment_method) {
                if (!($payment_method instanceof CardEntity)) {
                    return false;
                }

                foreach ($payment_method->getPaymentKeys() as $payment_key) {
                    if (!$payment_key->getPg()->isPayable()) {
                        return false;
                    }
                }

                return true;
            }
        );
    }

    /**
     * @return static
     */
    public static function getRepository(): self
    {
        return new self(PaymentMethodEntity::class);
    }
}
