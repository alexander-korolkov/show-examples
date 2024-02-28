<?php

namespace Fxtm\CopyTrading\Domain\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Fxtm\CopyTrading\Tests\Entities\Mt4TradeRecord;

class Mt4TradeRecordFixture extends BaseFixture
{
    private const CMD = 1;
    private const CLOSE_TIME = 0;
    private const FRS_REC_OPERATION = 'I';

    public function load(ObjectManager $manager)
    {
        foreach ($this->getFixtureData() as $elem) {
            $position = new Mt4TradeRecord();
            $position->setFrsServerId(self::TEST_FRS_SERVER_ID);
            $position->setFrsRecOperation(self::FRS_REC_OPERATION);
            $position->setSymbol($elem);
            $position->setLogin(self::TEST_LEADER_LOGIN);
            $position->setCmd(self::CMD);
            $position->setCloseTime(self::CLOSE_TIME);
            $manager->persist($position);
        }
        $manager->flush();
    }

    protected function getFixtureData(): array
    {
        return ['SOME', 'INDX', 'SP5', 'SYMB', 'UK100'];
    }
}
