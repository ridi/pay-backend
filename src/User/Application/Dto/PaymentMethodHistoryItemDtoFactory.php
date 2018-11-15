<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;
use RidiPay\User\Domain\PaymentMethodConstant;

class PaymentMethodHistoryItemDtoFactory
{
    private const ACTION_REGISTRATION = '등록';
    private const ACTION_DELETION = '삭제';

    /**
     * @param PaymentMethodEntity $payment_method
     * @return PaymentMethodHistoryItemDto
     * @throws UnsupportedPaymentMethodException
     */
    public static function createWithRegistration(PaymentMethodEntity $payment_method): PaymentMethodHistoryItemDto
    {
        switch ($payment_method->getType()) {
            case PaymentMethodConstant::TYPE_CARD:
                return new CardHistoryItemDto(
                    $payment_method,
                    self::ACTION_REGISTRATION,
                    $payment_method->getCreatedAt()
                );
            default:
                throw new UnsupportedPaymentMethodException();
        }
    }

    public static function createWithDeletion(PaymentMethodEntity $payment_method): PaymentMethodHistoryItemDto
    {
        switch ($payment_method->getType()) {
            case PaymentMethodConstant::TYPE_CARD:
                return new CardHistoryItemDto(
                    $payment_method,
                    self::ACTION_DELETION,
                    $payment_method->getDeletedAt()
                );
            default:
                throw new UnsupportedPaymentMethodException();
        }
    }
}
