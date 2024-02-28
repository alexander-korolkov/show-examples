<?php

namespace Fxtm\CopyTrading\Domain\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Fxtm\CopyTrading\Tests\Entities\Mt4ArsOrder;

class Mt4ArsOrderFixture extends BaseFixture
{
    private const CMD = 1;
    private const CLOSE_TIME = 0;

    public function load(ObjectManager $manager)
    {
        foreach ($this->getFixtureData() as $elem) {
            $position = new Mt4ArsOrder();
            $position->setSymbol($elem);
            $position->setLogin(self::TEST_LEADER_LOGIN);
            $position->setCmd(self::CMD);
            $position->setCloseTs(self::CLOSE_TIME);
            $manager->persist($position);
        }
        $manager->flush();
    }

    protected function getFixtureData(): array
    {
        return ['SOME', 'INDX', 'SP5', 'SYMB', 'UK100'];
    }
}
