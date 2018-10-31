<?php
declare(strict_types=1);

namespace RidiPay\Partner\Application\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Partner\Application\Dto\PartnerDto;
use RidiPay\Partner\Application\Dto\PartnerRegistrationDto;
use RidiPay\Partner\Domain\Entity\PartnerEntity;
use RidiPay\Partner\Domain\Exception\NotFoundPartnerException;
use RidiPay\Partner\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Partner\Domain\Repository\PartnerRepository;
use RidiPay\Transaction\Domain\Exception\AlreadyRegisteredPartnerException;

class PartnerAppService
{
    /**
     * @param string $name
     * @param string $password
     * @param bool $is_first_party
     * @return PartnerRegistrationDto
     * @throws AlreadyRegisteredPartnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function registerPartner(string $name, string $password, bool $is_first_party): PartnerRegistrationDto
    {
        $partner = PartnerRepository::getRepository()->findOneByName($name);
        if (!is_null($partner)) {
            throw new AlreadyRegisteredPartnerException();
        }

        $partner = new PartnerEntity($name, $password, $is_first_party);
        PartnerRepository::getRepository()->save($partner, true);

        return new PartnerRegistrationDto($partner);
    }

    /**
     * @param string $api_key
     * @param string $secret_key
     * @return int
     * @throws UnauthorizedPartnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function validatePartner(string $api_key, string $secret_key): int
    {
        $partner = PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($api_key));
        if (is_null($partner) || !$partner->isValidSecretKey($secret_key)) {
            throw new UnauthorizedPartnerException();
        }

        return $partner->getId();
    }


    /**
     * @param int $partner_id
     * @return PartnerDto
     * @throws NotFoundPartnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getPartner(int $partner_id): PartnerDto
    {
        $partner = PartnerRepository::getRepository()->findOneById($partner_id);
        if (is_null($partner)) {
            throw new NotFoundPartnerException();
        }

        return new PartnerDto($partner);
    }
}
