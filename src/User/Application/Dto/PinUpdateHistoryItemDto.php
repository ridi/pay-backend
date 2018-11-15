<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use OpenApi\Annotations as OA;
use RidiPay\User\Domain\Entity\UserActionHistoryEntity;

/**
 * @OA\Schema()
 */
class PinUpdateHistoryItemDto implements \JsonSerializable
{
    /**
     * @OA\Property(example="2018-11-06T10:14:09+09:00")
     *
     * @var string 결제 비밀번호 변경 일시
     */
    public $updated_at;

    /**
     * @param UserActionHistoryEntity $user_action_history
     */
    public function __construct(UserActionHistoryEntity $user_action_history)
    {
        $this->updated_at = $user_action_history->getCreatedAt();
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'updated_at' => $this->updated_at->format(DATE_ATOM)
        ];
    }
}
