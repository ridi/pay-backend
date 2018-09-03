<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Transaction\Domain\Entity\PartnerEntity;

class PartnerRepository extends BaseEntityRepository
{
    /**
     * @param int $id
     * @return null|PartnerEntity
     */
    public function findOneById(int $id): ?PartnerEntity
    {
        return $this->findOneBy(['id' => $id]);
    }

    /**
     * @param string $name
     * @return null|PartnerEntity
     */
    public function findOneByName(string $name): ?PartnerEntity
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @param string $api_key
     * @return null|PartnerEntity
     */
    public function findOneByApiKey(string $api_key): ?PartnerEntity
    {
        return $this->findOneBy(['api_key' => $api_key]);
    }

    /**
     * @return PartnerRepository
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository()
    {
        return EntityManagerProvider::getEntityManager()->getRepository(PartnerEntity::class);
    }
}
