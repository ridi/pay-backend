<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;

class Response
{
    /** @var string 정상처리 */
    const OK = '0000';

    /** @var string 기취소된 신용카드 거래 취소요청 */
    const ALREADY_CANCELLED = '8133';
}
