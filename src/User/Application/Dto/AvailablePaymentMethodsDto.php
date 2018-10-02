<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use OpenApi\Annotations as OA;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;

/**
 * @OA\Schema()
 */
class AvailablePaymentMethodsDto
{
    /**
     * @OA\Property()
     *
     * @var CardDto[]
     */
    public $cards;

    /**
     * @param PaymentMethodEntity[] $payment_methods
     * @throws UnsupportedPaymentMethodException
     */
    public function __construct(array $payment_methods)
    {
        $this->cards = [];

        foreach ($payment_methods as $payment_method) {
            $dto = PaymentMethodDtoFactory::create($payment_method);

            if ($dto instanceof CardDto) {
                $this->cards[] = $dto;
            }
        }
    }
}
