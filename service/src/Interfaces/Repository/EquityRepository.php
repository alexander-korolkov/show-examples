<?php

namespace Fxtm\CopyTrading\Interfaces\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Fxtm\CopyTrading\Domain\Entity\Equity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use DateTimeImmutable;

class EquityRepository extends ServiceEntityRepository
{
    /**
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Equity::class);
    }

    public function getEquitiesAsArray(int $accNo, ?DateTimeImmutable $fromDate, ?DateTimeImmutable $toDate): array
    {
        $dateConditionFrom = '';
        $dateConditionTo = '';
        if (null !== $fromDate) {
            $dateConditionFrom = 'AND eqt.date_time >= :date_from';
        };
        if (null !== $toDate) {
            $dateConditionTo = 'AND eqt.date_time <= :date_to';
        };
        $query = $this->getEntityManager()->createQuery("
            SELECT eqt.date_time, eqt.equity
            FROM " . Equity::class . " eqt
            WHERE eqt.acc_no = :acc_no
              {$dateConditionFrom}
              {$dateConditionTo}
            ORDER BY eqt.date_time
        ");
        $query->setParameter('acc_no', $accNo);
        if (null !== $fromDate) {
            $query->setParameter('date_from', $fromDate);
        };
        if (null !== $toDate) {
            $query->setParameter('date_to', $toDate);
        };
        return $query->getScalarResult();
    }

    public function add(int $accNo, DateTimeImmutable $date, string $equity, string $inout): Equity
    {
        $entity = new Equity();
        $entity->setProperties($accNo, $date, $equity, $inout);
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
        return $entity;
    }
}
