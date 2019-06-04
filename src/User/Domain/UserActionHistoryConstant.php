<?php
declare(strict_types=1);

namespace RidiPay\User\Domain;

class UserActionHistoryConstant
{
    public const ACTION_REGISTER_CARD = 'REGISTER_CARD';
    public const ACTION_DELETE_CARD = 'DELETE_CARD';
    public const ACTION_CHANGE_CARD = 'CHANGE_CARD';
    public const ACTION_UPDATE_PIN = 'UPDATE_PIN';
}
