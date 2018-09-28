<?php
declare(strict_types=1);

namespace RidiPay\Partner\Application\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Partner\Application\Dto\RegisterPartnerDto;
use RidiPay\Partner\Domain\Entity\PartnerEntity;
use RidiPay\Partner\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Partner\Domain\Repository\PartnerRepository;
use RidiPay\Transaction\Domain\Exception\AlreadyRegisteredPartnerException;

class PartnerAppService
{
    /**
     * @param string $name
     * @param string $password
     * @param bool $is_first_party
     * @return RegisterPartnerDto
     * @throws AlreadyRegisteredPartnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function registerPartner(string $name, string $password, bool $is_first_party): RegisterPartnerDto
    {
        $partner = PartnerRepository::getRepository()->findOneByName($name);
        if (!is_null($partner)) {
            throw new AlreadyRegisteredPartnerException();
        }

        $partner = new PartnerEntity($name, $password, $is_first_party);
        PartnerRepository::getRepository()->save($partner, true);

        return new RegisterPartnerDto($partner);
    }

    /**
     * @param string $api_key
     * @param string $secret_key
     * @return int
     * @throws UnauthorizedPartnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function validatePartner(string $api_key, string $secret_key): int
    {
        $partner = PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($api_key));
        if (is_null($partner) || !$partner->isValidSecretKey($secret_key)) {
            throw new UnauthorizedPartnerException();
        }

        return $partner->getId();
    }
}
