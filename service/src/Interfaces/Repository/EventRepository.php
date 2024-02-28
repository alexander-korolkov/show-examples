<?php


namespace Fxtm\CopyTrading\Interfaces\Repository;



use Fxtm\CopyTrading\Domain\Model\Event\EventEntity;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

interface EventRepository
{

    /**
     * @param EventEntity $event
     * @return mixed
     * @throws EventException
     */
    function store(EventEntity $event) : void;

    /**
     * @param AccountNumber $accountNumber
     * @param string $type
     * @return array
     * @throws EventException
     */
    function findByAccountAndType(AccountNumber $accountNumber, string $type) : array;
}