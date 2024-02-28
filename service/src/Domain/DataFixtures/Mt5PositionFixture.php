<?php

namespace Fxtm\CopyTrading\Domain\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Fxtm\CopyTrading\Tests\Entities\Mt5Position;

class Mt5PositionFixture extends BaseFixture
{
    private const FRS_REC_OPERATION = 'A';

    public function load(ObjectManager $manager)
    {
        foreach ($this->getFixtureData() as $elem) {
            $position = new Mt5Position();
            $position->setFrsServerId(self::TEST_FRS_SERVER_ID);
            $position->setFrsRecOperation(self::FRS_REC_OPERATION);
            $position->setSymbol($elem);
            $position->setLogin(self::TEST_LEADER_LOGIN);
            $position->setPosition(self::TEST_POSITION);
            $manager->persist($position);
        }
        $manager->flush();
    }

    protected function getFixtureData(): array
    {
        return ['SOME', 'INDX', 'SP5', 'SYMB', 'UK100', 'UK500'];
    }
}
