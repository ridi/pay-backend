<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Transaction\Entity\PartnerEntity;
use RidiPay\Transaction\Exception\AlreadyRegisteredPartnerException;
use RidiPay\Transaction\Repository\PartnerRepository;
use RidiPay\Transaction\Dto\PartnerDto;

class PartnerService
{
    /**
     * @param string $name
     * @param string $password
     * @param bool $is_first_party
     * @return PartnerDto
     * @throws AlreadyRegisteredPartnerException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function registerPartner(string $name, string $password, bool $is_first_party): PartnerDto
    {
        $partner = PartnerRepository::getRepository()->findOneByName($name);
        if (!is_null($partner)) {
            throw new AlreadyRegisteredPartnerException();
        }

        $api_key = Uuid::uuid4()->toString();
        $secret_key = Uuid::uuid4()->toString();

        $partner = new PartnerEntity($name, $password, $api_key, $secret_key, $is_first_party);
        PartnerRepository::getRepository()->save($partner, true);

        return new PartnerDto($api_key, $secret_key);
    }
}
