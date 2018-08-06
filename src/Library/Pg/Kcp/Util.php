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

    /**
     * KCP 결제 모듈의 반환값 문자열을 연관 배열로 변환합니다.
     *
     * @param string $serialized
     * @return array
     */
    public static function parsePayPlusCliOutput(string $serialized): array
    {
        $exploded = \explode("\x1f", $serialized);

        $parsed = \array_reduce($exploded, function ($carry , $item) {
            $kv = \explode('=', $item);
            if (count($kv) === 2) {
                [$k, $v] = $kv;
                $carry[$k] = $v;
            }
            return $carry;
        }, []);

        return $parsed;
    }

    /**
     * 연관 배열을 `key1=value1,key2=value2` 형태의 문자열로 변환합니다.
     *
     * @param array $array
     * @param string $separator
     * @param bool $append_separator
     * @return string
     */
    public static function flattenAssocArray(
        array $array,
        string $separator = ',',
        bool $append_separator = false
    ): string {
        $keys = \array_keys($array);
        $items = \array_map(function ($key) use ($array) {
            return "$key=$array[$key]";
        }, $keys);

        $flattened = \implode($separator, $items);
        if ($append_separator) {
            $flattened .= $separator;
        }

        return $flattened;
    }
}
