<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use OpenApi\Annotations as OA;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;

/**
 * @OA\Schema()
 */
class CardHistoryItemDto extends PaymentMethodHistoryItemDto
{
    /**
     * @OA\Property(example="541654")
     *
     * @var string 발급자 식별 번호(카드 번호 앞 6자리)
     */
    public $iin;

    /**
     * @param PaymentMethodEntity $payment_method
     * @param string $action
     * @param \DateTime $processed_at
     * @throws \Exception
     */
    public function __construct(PaymentMethodEntity $payment_method, string $action, \DateTime $processed_at)
    {
        parent::__construct($payment_method, $action, $processed_at);

        $this->iin = $payment_method->getCardForOneTimePayment()->getIin();
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'iin' => $this->iin
            ]
        );
    }
}
