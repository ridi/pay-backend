<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Transaction\Domain\Entity\PartnerEntity;
use RidiPay\Transaction\Domain\Exception\AlreadyRegisteredPartnerException;
use RidiPay\Transaction\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Transaction\Domain\Repository\PartnerRepository;
use RidiPay\Transaction\Application\Dto\RegisterPartnerDto;

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

        $api_key = Uuid::uuid4()->toString();
        $secret_key = Uuid::uuid4()->toString();

        $partner = new PartnerEntity($name, $password, $api_key, $secret_key, $is_first_party);
        PartnerRepository::getRepository()->save($partner, true);

        return new RegisterPartnerDto($api_key, $secret_key);
    }

    /**
     * @param string $api_key
     * @param string $secret_key
     * @throws UnauthorizedPartnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function validatePartner(string $api_key, string $secret_key): void
    {
        $partner = PartnerRepository::getRepository()->findOneByApiKey($api_key);
        if (is_null($partner) || !$partner->isValidSecretKey($secret_key)) {
            throw new UnauthorizedPartnerException();
        }
    }

    /**
     * @param string $api_key
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getPartnerIdByApiKey(string $api_key): int
    {
        $partner = PartnerRepository::getRepository()->findOneByApiKey($api_key);

        return $partner->getId();
    }
}
