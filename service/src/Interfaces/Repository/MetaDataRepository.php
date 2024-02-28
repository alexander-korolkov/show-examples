<?php


namespace Fxtm\CopyTrading\Interfaces\Repository;


use Fxtm\CopyTrading\Domain\Entity\MetaData\MetaData;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

interface MetaDataRepository
{

    public function getMetaData(AccountNumber $account): MetaData;

}