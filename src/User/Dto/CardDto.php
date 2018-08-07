<?php
declare(strict_types=1);

namespace RidiPay\User\Dto;

use RidiPay\User\Dto\AbstractPaymentMethodDto;
use RidiPay\User\Entity\CardEntity;

class CardDto extends AbstractPaymentMethodDto
{
    /** @var string 발급자 식별 번호(카드 번호 앞 6자리) */
    private $iin;

    /** @var string 카드 발급사명 */
    private $issuer_name;

    /**
     * @param CardEntity $card
     */
    public function __construct(CardEntity $card)
    {
        parent::__construct($card->getPaymentMethod());
        
        $this->iin = $card->getIin();
        $this->issuer_name = $card->getCardIssuer()->getName();
    }
}
