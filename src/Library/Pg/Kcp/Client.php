<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;

/**
 * NHN KCP 결제 모듈을 래핑한 클라이언트입니다.
 *
 * 주의: 연관 배열들의 키 순서를 바꾸지 마세요! PHP 연관 배열은 순서가 매겨진 맵이고, 결제 모듈 호출시 파라미터 순서가 유지되어야 합니다.
 *
 * 정의된 상수들은 임의로 변경할 수 없습니다.
 */
class Client {
    /** @var string KCP 프로덕션 결제 서버 주소 */
    private const GW_URL = 'paygw.kcp.co.kr';

    /** @var string KCP 결제 서버 포트  */
    private const GW_PORT = '8090';

    /** @var string KCP 테스트 결제 서버 주소 */
    private const TEST_GW_URL = 'testpaygw.kcp.co.kr';

    /** @var string KCP 테스트 상점 코드 */
    private const TEST_SITE_CODE = 'BA001';

    /** @var string KCP 테스트 상점 키  */
    private const TEST_SITE_KEY = '2T5.LgLrH--wbufUOvCqSNT__';

    /** @var string KCP 테스트 그룹 ID */
    private const TEST_GROUP_ID = 'BA0011000348';

    /** @var int 로깅 수준 */
    private const LOG_LEVEL = 3;

    /** @var int 결제 모듈이 EUC-KR 인코딩을 사용하도록 설정 */
    private const OPT_EUC_KR = 0;

    /** @var int 결제 모듈이 UTF-8 인코딩을 사용하도록 설정 */
    private const OPT_UTF_8 = 1;

    /** @var string 승인 금액 통화 */
    private const CURRENCY_KRW = '410';

    /** @var string 에스크로 사용 여부 */
    private const ESCROW_NONE = 'N';

    /** @var string 배치 키 발급 요청 코드  */
    private const TRANSACTION_CODE_AUTH = '00300001';
    private const CARD_TRANSACTION_TYPE_AUTH = '12100000';
    private const SIGN_TRANSACTION_TYPE_AUTH = '0001';

    /** @var string 결제 승인 요청 코드 */
    private const TRANSACTION_CODE_ORDER = '00100000';
    private const CARD_TRANSACTION_TYPE_BATCH_ORDER = '11511000';

    /** @var string 결제 취소 요청 코드 */
    private const TRANSACTION_CODE_CANCEL = '00200000';

    /** @var string 승인 금액 전체 취소 */
    private const MOD_TYPE_CANCEL_ORDER_FULL = 'STSC';

    /** @var string 승인 금액 부분 취소 */
    private const MOD_TYPE_CANCEL_ORDER_PART = 'STPC';

    /** @var string 상점 코드 */
    private $site_code;

    /** @var string 상점 키 */
    private $site_key;

    /** @var string KCP 결제 서버 주소 */
    private $gw_url;

    /** @var string 상점 그룹 ID */
    private $group_id;

    /** @var string 로그 디렉토리 */
    private $log_dir;

    /** @var string 결제 모듈 경로 */
    private $module_path;

    /**
     * @param string $site_code KCP에서 전달받은 사이트 코드
     * @param string $site_key KCP에서 전달받은 사이트 키
     * @param string $group_id KCP 상점관리자 페이지의 결제관리 > 일반결제> 배치결제 > 그룹등록 메뉴에서 생성한 그룹 ID
     * @param string $log_dir 로그 파일들을 쓸 디렉토리
     * @param string $gw_url KCP 결제 서버 주소
     */
    public function __construct(
        string $site_code,
        string $site_key,
        string $group_id,
        string $log_dir,
        string $gw_url = self::GW_URL
    ) {
        $this->site_code = $site_code;
        $this->site_key = $site_key;
        $this->group_id = $group_id;
        $this->gw_url = $gw_url;
        $this->log_dir = $log_dir;
        $this->module_path = \realpath(__DIR__ . '/pp_cli');
    }

    /**
     * @param string $log_dir 로그 파일들을 쓸 디렉토리
     * @return Client
     */
    public static function getTestClient(string $log_dir): Client
    {
        return new self(
            self::TEST_SITE_CODE,
            self::TEST_SITE_KEY,
            self::TEST_GROUP_ID,
            $log_dir,
            self::TEST_GW_URL
        );
    }

    /**
     * @param Card $card
     * @return array
     */
    public function requestBatchKey(Card $card): array
    {
        $sign_tx_type = self::SIGN_TRANSACTION_TYPE_AUTH;
        $card_tx_type = self::CARD_TRANSACTION_TYPE_AUTH;
        $card = (string) $card;

        $payx_data = "payx_data=\x1f\x1e" .
            "card=card_mny=\x1fcard_tx_type=$card_tx_type\x1f$card\x1e" .
            "auth=sign_txtype=$sign_tx_type\x1fgroup_id=$this->group_id\x1f\x1e";

        $params = [
            'payx_data' => $payx_data,
        ];

        return self::execPayPlusClient(self::TRANSACTION_CODE_AUTH, $params);
    }

    /**
     * @param string $batch_key 발급받은 배치 키
     * @param Order $order 주문 정보
     * @param int $installment_months 할부 개월수
     * @return array
     */
    public function batchOrder(string $batch_key, Order $order, int $installment_months = 0): array
    {
        $price = $order->getGoodPrice();
        $currency = self::CURRENCY_KRW;
        $escrow = self::ESCROW_NONE;
        $card_tx_type = self::CARD_TRANSACTION_TYPE_BATCH_ORDER;
        $installments = \str_pad((string) $installment_months, 2, '0');

        $payx_data = "payx_data=common=amount=$price\x1fcurrency=$currency\x1fescw_mod=$escrow\x1f\x1e" .
            "card=card_mny=$price\x1fcard_tx_type=$card_tx_type\x1fquota=$installments\x1f" .
            "bt_group_id=$this->group_id\x1fbt_batch_key=$batch_key\x1f\x1e";

        $params = [
            'ordr_idxx' => $order->getId(),
            'payx_data' => $payx_data,
            'ordr_data' => (string) $order,
        ];

        return self::execPayPlusClient(self::TRANSACTION_CODE_ORDER, $params);
    }

    /**
     * @param string $kcp_tno KCP 측 주문번호
     * @param string $reason 취소 사유
     * @return array
     */
    public function cancelTransaction(string $kcp_tno, string $reason): array {
        $reason = "\"$reason\"";
        $mod_type = self::MOD_TYPE_CANCEL_ORDER_FULL;

        $params = [
            'modx_data' => "mod_data=tno=$kcp_tno\x1fmod_type=$mod_type\x1fmod_desc=$reason\x1f",
        ];

        return self::execPayPlusClient(self::TRANSACTION_CODE_CANCEL, $params);
    }

    /**
     *
     * @param string $transaction_code
     * @param array $transaction_params
     * @return array
     */
    private function execPayPlusClient(string $transaction_code, array $transaction_params): array
    {
        $params = \array_merge(
            [
                'home' => $this->module_path,
                'site_cd' => $this->site_code,
                'site_key' => $this->site_key,
                'tx_cd' => $transaction_code,
                'pa_url' => $this->gw_url,
                'pa_port' => self::GW_PORT,
            ],
            $transaction_params,
            [
                'log_level' => self::LOG_LEVEL,
                'log_path' => $this->log_dir,
                'opt' => self::OPT_UTF_8,
            ]
        );

        $command = \implode(' ', [
            $this->module_path,
            '-h',
            Util::flattenAssocArray($params),
        ]);

        $output = \exec($command, $_, $return_var);
        return Util::parsePayPlusCliOutput($output);
    }
}
