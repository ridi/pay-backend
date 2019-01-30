<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use OpenApi\Annotations as OA;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;

abstract class PaymentMethodHistoryItemDto implements \JsonSerializable
{
    /**
     * @OA\Property(example="550E8400-E29B-41D4-A716-446655440000")
     *
     * @var string 결제 수단 UUID
     */
    public $payment_method_id;

    /**
     * @OA\Property(example="등록")
     *
     * @var string 등록/삭제
     */
    public $action;

    /**
     * @OA\Property(example="2018-11-06T10:14:09+09:00")
     *
     * @var \DateTime 등록/삭제 일시
     */
    public $processed_at;

    /**
     * @param PaymentMethodEntity $payment_method
     * @param string $action
     * @param \DateTime $processed_at
     * @throws \Exception
     */
    protected function __construct(PaymentMethodEntity $payment_method, string $action, \DateTime $processed_at)
    {
        $this->payment_method_id = $payment_method->getUuid()->toString();
        $this->action = $action;
        $this->processed_at = $processed_at;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'payment_method_id' => $this->payment_method_id,
            'action' => $this->action,
            'processed_at' => $this->processed_at->format(DATE_ATOM)
        ];
    }
}
