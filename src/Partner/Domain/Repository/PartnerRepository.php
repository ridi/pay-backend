<?php
declare(strict_types=1);

namespace RidiPay\Partner\Domain\Repository;

use Ramsey\Uuid\UuidInterface;
use RidiPay\Library\BaseEntityRepository;
use RidiPay\Partner\Domain\Entity\PartnerEntity;

class PartnerRepository extends BaseEntityRepository
{
    /**
     * @param int $id
     * @return null|PartnerEntity
     */
    public function findOneById(int $id): ?PartnerEntity
    {
        return $this->findOneBy([
            'id' => $id,
            'is_valid' => true
        ]);
    }

    /**
     * @param string $name
     * @return null|PartnerEntity
     */
    public function findOneByName(string $name): ?PartnerEntity
    {
        return $this->findOneBy([
            'name' => $name,
            'is_valid' => true
        ]);
    }

    /**
     * @param UuidInterface $api_key
     * @return null|PartnerEntity
     */
    public function findOneByApiKey(UuidInterface $api_key): ?PartnerEntity
    {
        return $this->findOneBy([
            'api_key' => $api_key,
            'is_valid' => true
        ]);
    }

    /**
     * @return PartnerRepository
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository(): self
    {
        return new self(PartnerEntity::class);
    }
}
