<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use RidiPay\User\Domain\Entity\CardEntity;

class CardDto extends PaymentMethodDto
{
    /** @var string 발급자 식별 번호(카드 번호 앞 6자리) */
    public $iin;

    /** @var string 카드 발급사명 */
    public $issuer_name;

    /** @var string 카드 플레이트 색상 */
    public $color;

    /** @var string 카드 발급사 로고 이미지 URL */
    public $logo_image_url;

    /** @var string[] 연결된 정기 결제 서비스명 */
    public $subscriptions;

    /**
     * @param CardEntity $card
     * @throws \Exception
     */
    public function __construct(CardEntity $card)
    {
        parent::__construct($card->getPaymentMethod());
        
        $this->iin = $card->getIin();
        $this->issuer_name = $card->getCardIssuer()->getName();
        $this->color = $card->getCardIssuer()->getColor();
        $this->logo_image_url = $card->getCardIssuer()->getLogoImageUrl();
        $this->subscriptions = []; // TODO: 구현
    }
}
