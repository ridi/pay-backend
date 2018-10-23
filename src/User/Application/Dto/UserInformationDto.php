<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use RidiPay\User\Domain\Entity\UserEntity;

class UserInformationDto
{
    /** @var AvailablePaymentMethodsDto */
    public $payment_methods;

    /** @var bool */
    public $has_pin;

    /** @var null|bool */
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
