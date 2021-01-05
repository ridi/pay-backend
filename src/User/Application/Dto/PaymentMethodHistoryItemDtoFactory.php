<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use RidiPay\User\Domain\Entity\CardEntity;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;

class PaymentMethodHistoryItemDtoFactory
{
    public const ACTION_REGISTRATION = '등록';
    public const ACTION_DELETION = '삭제';

    /**
     * @param PaymentMethodEntity $payment_method
     * @return PaymentMethodHistoryItemDto
     * @throws UnsupportedPaymentMethodException
     */
    public static function createWithRegistration(PaymentMethodEntity $payment_method): PaymentMethodHistoryItemDto
    {
        if ($payment_method instanceof CardEntity) {
            return new CardHistoryItemDto(
                $payment_method,
                self::ACTION_REGISTRATION,
                $payment_method->getCreatedAt()
            );
        } else {
            throw new UnsupportedPaymentMethodException();
        }
    }

    /**
     * @param PaymentMethodEntity $payment_method
     * @return PaymentMethodHistoryItemDto
     * @throws UnsupportedPaymentMethodException
     */
    public static function createWithDeletion(PaymentMethodEntity $payment_method): PaymentMethodHistoryItemDto
    {
        if ($payment_method instanceof CardEntity) {
            return new CardHistoryItemDto(
                $payment_method,
                self::ACTION_DELETION,
                $payment_method->getDeletedAt()
            );
        } else {
            throw new UnsupportedPaymentMethodException();
        }
    }
}
