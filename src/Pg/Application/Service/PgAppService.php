<?php
declare(strict_types=1);

namespace RidiPay\Pg\Application\Service;

use RidiPay\Pg\Application\Dto\PgDto;
use RidiPay\Pg\Domain\Entity\PgEntity;
use RidiPay\Pg\Domain\Repository\PgRepository;

class PgAppService
{
    /**
     * @param int $pg_id
     * @return PgDto
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getPgById(int $pg_id): PgDto
    {
        $pg = PgRepository::getRepository()->findOneById($pg_id);

        return new PgDto($pg);
    }

    /**
     * @return PgDto
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getActivePg(): PgDto
    {
        $pg = PgRepository::getRepository()->findActiveOne();

        return new PgDto($pg);
    }
}
