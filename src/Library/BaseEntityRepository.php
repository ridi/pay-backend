<?php
declare(strict_types=1);

namespace RidiPay\Library;

use Doctrine\ORM\EntityRepository;

class BaseEntityRepository extends EntityRepository
{
    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getDbalConnection()
    {
        return $this->getEntityManager()->getConnection();
    }

    /**
     * @param $entity
     * @param bool $is_flush_single_entity 단일 엔터티만 flush 할지 여부. flush 함수의 상황별 주석 참고.
     * @throws \Exception
     */
    public function save($entity, bool $is_flush_single_entity = false)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);

        $this->flush($entity, $is_flush_single_entity);
    }

    /**
     * @param $entity
     * @param bool $is_flush_single_entity 단일 엔터티만 flush 할지 여부. 아래 상황별 주석 참고.
     * @throws \Exception
     */
    public function remove($entity, bool $is_flush_single_entity = false)
    {
        $em = $this->getEntityManager();
        $em->remove($entity);

        $this->flush($entity, $is_flush_single_entity);
    }

    /**
     * @param $entity
     * @param bool $is_flush_single_entity 아래 주석 참고
     */
    private function flush($entity, bool $is_flush_single_entity)
    {
        $em = $this->getEntityManager();

        if ($is_flush_single_entity) {
            /*
             * - 사용할 경우: Association Entity가 없다고 보장되는 단일 Entity의 저장에만 사용. 성능 향상.
             * - 상세 설명:
             *   - 단일 엔터티 flush. UnitOfWork의 모든 Change Set을 확인하지 않아 성능 향상.
             *   - Association Entity는 동시에 적용되지 않는 단점이 존재.
             */
            $em->flush($entity);
        } else {
            /*
             * - 사용할 경우: Association Entity가 존재하는 경우에 사용. 성능 저하.
             * - 상세 설명:
             *   - UnitOfWork에서 관리하는 모든 엔터티들의 Change Set을 검사하고, 변경사항이 있는 모든 엔터티를 flush. 이로 인한 성능 저하.
             *   - 또한 이 함수 호출시 명시한 $entity만 flush 되지 않고 UnitOfWork에서 관리하는 다른 엔터티들도 저장될 수 있는 문제점도 존재함.
             *   - Doctrine의 버그(혹은 설계 방향?)으로 인해 $entity를 명시할 경우 Association Entity는 동시에 적용되지 않아 이를 사용.
             */
            $em->flush();
        }
    }
}
