<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;

class Util
{
    const RECEIPT_LANG_KO = 'card_bill';
    const RECEIPT_LANG_EN = 'card_bill_eng';

    /**
     * 신용카드 매출전표를 조회할 수 있는 URL을 생성합니다.
     *
     * @param string $kcp_tno
     * @param string $order_no
     * @param int $good_price
     * @param string $lang
     * @param bool $production
     * @return string
     */
    public static function buildReceiptUrl(
        string $kcp_tno,
        string $order_no,
        int $good_price,
        string $lang = self::RECEIPT_LANG_KO,
        bool $production = true
    ): string {
        $qs = \http_build_query([
            'cmd' => $lang,
            'tno' => $kcp_tno,
            'order_no' => $order_no,
            'trade_mony' => $good_price,
        ]);

        if ($production) {
            return "https://admin8.kcp.co.kr/assist/bill.BillActionNew.do?$qs";
        } else {
            return "https://testadmin8.kcp.co.kr/assist/bill.BillActionNew.do?$qs";
        }
    }
}
