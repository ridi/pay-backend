<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use OpenApi\Annotations as OA;
use RidiPay\User\Domain\Entity\UserActionHistoryEntity;

/**
 * @OA\Schema()
 */
class OnetouchPaySettingChangeHistoryItemDto implements \JsonSerializable
{
    /**
     * @OA\Property(example=true)
     *
     * @var bool
     */
    public $enable_onetouch_pay;

    /**
     * @OA\Property(example="2018-11-06T10:14:09+09:00")
     *
     * @var string 원터치 결제 설정 변경 일시
     */
    public $updated_at;

    /**
     * @param UserActionHistoryEntity $user_action_history
     */
    public function __construct(UserActionHistoryEntity $user_action_history)
    {
        if (!$user_action_history->isEnableOnetouchPay() && !$user_action_history->isDisableOnetouchPay()) {
            throw new \InvalidArgumentException();
        }

        $this->enable_onetouch_pay = $user_action_history->isEnableOnetouchPay();
        $this->updated_at = $user_action_history->getCreatedAt();
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'enable_onetouch_pay' => $this->enable_onetouch_pay,
            'updated_at' => $this->updated_at->format(DATE_ATOM)
        ];
    }
}
