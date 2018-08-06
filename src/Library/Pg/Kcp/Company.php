<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;

class Company
{
    /** @var string KB국민카드 */
    const KOOKMIN = 'CCKM';

    /** @var string NH농협카드 */
    const NONGHYUP = 'CCNH';

    /** @var string 신세계한미 */
    const SHINSEGAE_HANMI = 'CCSG';

    /** @var string 씨티카드 */
    const CITI = 'CCCT';

    /** @var string 한미카드 */
    const HANMI = 'CCHM';

    /** @var string 해외비자 */
    const VISA = 'CVSF';

    /** @var string 롯데아멕스카드 */
    const LOTTE_AMEX = 'CCAM';

    /** @var string 롯데카드 */
    const LOTTE = 'CCLO';

    /** @var string BC카드 */
    const BC = 'CCBC';

    /** @var string 우리카드 */
    const WOORI = 'CCBC';

    /** @var string 하나SK카드 */
    const HANA_SK = 'CCHN';

    /** @var string 삼성카드 */
    const SAMSUNG = 'CCSS';

    /** @var string 광주카드 */
    const GWANGJU = 'CCKJ';

    /** @var string 수협카드 */
    const SUHYUP = 'CCSU';

    /** @var string 신협카드 */
    const SHINHYUP = 'CCBC';

    /** @var string 전북카드 */
    const JEONBUK = 'CCJB';

    /** @var string 제주카드 */
    const JEJU = 'CCCJ';

    /** @var string 신한카드 */
    const SHINHAN = 'CCLG';

    /** @var string 해외마스터 */
    const MASTER = 'CMCF';

    /** @var string 해외JCB */
    const JCB = 'CJCF';

    /** @var string 외환카드 */
    const KOREA_EXCHANGE = 'CCKE';

    /** @var string 현대증권카드 */
    const HYUNDAI_SECURITIES = 'CCBC';

    /** @var string 현대카드 */
    const HYUNDAI = 'CCDI';

    /** @var string 저축카드 */
    const SAVINGS = 'CCBC';

    /** @var string 산업카드 */
    const DEVELOPMENT = 'CCBC';

    /** @var string 은련카드 */
    const UNION = 'CCUF';

    /** @var array 회사 코드와 한국어 이름의 대응 */
    const COMPANY_NAME_MAPPING_KO = [
        self::KOOKMIN => 'KB국민카드',
        self::NONGHYUP => 'NH농협카드',
        self::SHINSEGAE_HANMI => '신세계한미',
        self::CITI => '씨티카드',
        self::HANMI => '한미카드',
        self::VISA => '해외비자',
        self::LOTTE_AMEX => '롯데아멕스카드',
        self::LOTTE => '롯데카드',
        self::BC => 'BC카드',
        self::HANA_SK => '하나SK카드',
        self::SAMSUNG => '삼성카드',
        self::GWANGJU => '광주카드',
        self::SUHYUP => '수협카드',
        self::JEONBUK => '전북카드',
        self::JEJU => '제주카드',
        self::SHINHAN => '신한카드',
        self::MASTER => '해외마스터',
        self::JCB => '해외JCB',
        self::KOREA_EXCHANGE => '외환카드',
        self::HYUNDAI => '현대카드',
        self::UNION => '은련카드',
    ];

    /** @var array 발급사와 매입사의 매응 */
    const ISSUER_ACQUIRER_MAPPING = [
        self::KOOKMIN => self::KOOKMIN,
        self::NONGHYUP => self::NONGHYUP,
        self::SHINSEGAE_HANMI => self::BC,
        self::CITI => self::BC,
        self::HANMI => self::BC,
        self::VISA => self::KOREA_EXCHANGE,
        self::LOTTE_AMEX => self::LOTTE_AMEX,
        self::LOTTE => self::LOTTE_AMEX,
        self::BC => self::BC,
        self::WOORI => self::BC,
        self::HANA_SK => self::HANA_SK,
        self::SAMSUNG => self::SAMSUNG,
        self::GWANGJU => self::BC,
        self::SUHYUP => self::BC,
        self::SHINHYUP => self::BC,
        self::JEONBUK => self::BC,
        self::JEJU => self::BC,
        self::SHINHAN => self::SHINHAN,
        self::MASTER => self::KOREA_EXCHANGE,
        self::JCB => self::KOREA_EXCHANGE,
        self::KOREA_EXCHANGE => self::KOREA_EXCHANGE,
        self::HYUNDAI_SECURITIES => self::BC,
        self::HYUNDAI => self::HYUNDAI,
        self::SAVINGS => self::BC,
        self::DEVELOPMENT => self::BC,
        self::UNION => self::BC,
        self::SAVINGS => self::BC,
    ];

    /**
     * 회사 코드로 한국어 회사명 조회.
     *
     * @param string $company
     * @return null|string
     */
    public static function getKoreanName(string $company): ?string
    {
        return self::COMPANY_NAME_MAPPING_KO[$company];
    }

    /**
     * 카드 발급사 코드로 전표 매입사 코드 조회.
     *
     * @param string $issuer
     * @return null|string
     */
    public static function getAcquirerFromIssuer(string $issuer): ?string
    {
        return self::ISSUER_ACQUIRER_MAPPING[$issuer];
    }
}
