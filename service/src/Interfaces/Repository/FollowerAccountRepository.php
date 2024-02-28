<?php

namespace Fxtm\CopyTrading\Interfaces\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Fxtm\CopyTrading\Domain\Entity\FollowerAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

class FollowerAccountRepository extends ServiceEntityRepository
{
    /**
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, FollowerAccount::class);
    }

    public function getLoginsAsArray(array $statuses = []): array
    {
        $statusCondition = '';
        if ($statuses) {
            $statusCondition = 'WHERE fa.status IN (' . implode(',', $statuses) . ')';
        };
        $query = $this->getEntityManager()->createQuery("
            SELECT fa.accNo
            FROM " . FollowerAccount::class . " fa
            {$statusCondition}
            ORDER BY fa.accNo
        ");
        $result = [];
        foreach ($query->getScalarResult() as $record) {
            $result[] = (int) $record['accNo'];
        }
        return $result;
    }
}