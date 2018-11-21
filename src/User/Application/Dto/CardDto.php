<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use OpenApi\Annotations as OA;
use RidiPay\Transaction\Application\Dto\SubscriptionDto;
use RidiPay\Transaction\Application\Service\SubscriptionAppService;
use RidiPay\User\Domain\Entity\CardEntity;

/**
 * @OA\Schema()
 */
class CardDto extends PaymentMethodDto
{
    /**
     * @OA\Property(example="541654")
     *
     * @var string 발급자 식별 번호(카드 번호 앞 6자리)
     */
    public $iin;

    /**
     * @OA\Property(example="신한")
     *
     * @var string 카드 발급사명
     */
    public $issuer_name;

    /**
     * @OA\Property(example="#00FF00")
     *
     * @var string 카드 플레이트 색상
     */
    public $color;

    /**
     * @OA\Property()
     *
     * @var string 카드 발급사 로고 이미지 URL
     */
    public $logo_image_url;

    /**
     * @OA\Property(
     *   @OA\Schema(
     *     type="array",
     *     @OA\Items(type="string"),
     *   )
     * )
     *
     * @var string[] 연결된 정기 결제 서비스명
     */
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
        $this->subscriptions = array_unique(array_map(
            function (SubscriptionDto $subscription) {
                return $subscription->product_name;
            },
            SubscriptionAppService::getSubscriptions($card->getPaymentMethod()->getId())
        ));
    }
}
