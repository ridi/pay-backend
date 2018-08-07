<?php
declare(strict_types=1);

namespace RidiPay\User\Dto;

use RidiPay\User\Dto\CardDto;
use RidiPay\User\Entity\PaymentMethodEntity;

class PaymentMethodsDto
{
    /** @var CardDto[] */
    public $cards;

    /**
     * @param PaymentMethodEntity[] $payment_methods
     */
    public function __construct(array $payment_methods)
    {
        $this->cards = [];

        foreach ($payment_methods as $payment_method) {
            if ($payment_method->isCard()) {
                // 단건 결제용 카드와 정기 결제용 카드는 PG 연동이 분기 가능성을 고려해서 나눠진 것 뿐이고, 두 카드는 동일한 카드라서 단건 결제용 카드를 외부로 전달한다.
                $this->cards[] = new CardDto($payment_method->getCardForOneTimePayment());
            }
        }
    }
}
