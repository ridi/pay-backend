<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use OpenApi\Annotations as OA;
use RidiPay\User\Domain\Entity\UserEntity;

/**
 * @OA\Schema()
 */
class UserInformationDto
{
    /**
     * @OA\Property()
     *
     * @var AvailablePaymentMethodsDto
     */
    public $payment_methods;

    /**
     * @OA\Property(example=true)
     *
     * @var bool
     */
    public $has_pin;

    /**
     * @OA\Property(example=false, nullable=true)
     *
     * @var null|bool
     */
    public $is_using_onetouch_pay;

    /**
     * @param AvailablePaymentMethodsDto $payment_methods
     * @param UserEntity $user
     */
    public function __construct(AvailablePaymentMethodsDto $payment_methods, UserEntity $user)
    {
        $this->payment_methods = $payment_methods;
        $this->has_pin = $user->hasPin();
        $this->is_using_onetouch_pay = $user->isUsingOnetouchPay();
    }
}
