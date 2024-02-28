<?php

namespace Fxtm\CopyTrading\Interfaces\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Fxtm\CopyTrading\Domain\Entity\LeaderAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

class LeaderAccountRepository extends ServiceEntityRepository
{
    /**
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, LeaderAccount::class);
    }

    public function getLoginsAsArray(array $statuses = []): array
    {
        $statusCondition = '';
        if ($statuses) {
            $statusCondition = 'WHERE la.status IN (' . implode(',', $statuses) . ')';
        };
        $query = $this->getEntityManager()->createQuery("
            SELECT la.accNo
            FROM " . LeaderAccount::class . " la
            {$statusCondition}
            ORDER BY la.accNo
        ");
        $result = [];
        foreach ($query->getScalarResult() as $record) {
            $result[] = (int) $record['accNo'];
        }
        return $result;
    }
}
