<?php

namespace Fxtm\CopyTrading\Domain\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Fxtm\CopyTrading\Tests\Entities\Mt4SymbolGroup;

class Mt4SymbolGroupFixture extends BaseFixture
{
    public function load(ObjectManager $manager)
    {
        foreach ($this->getFixtureData() as $elem) {
            $symbol = new Mt4SymbolGroup();
            $symbol->setFrsServerId(Mt5PositionFixture::TEST_FRS_SERVER_ID);
            $symbol->setName($elem);
            $symbol->setIndex(self::TEST_MT4_SYMBOL_TYPE);
            $manager->persist($symbol);
        }
        $manager->flush();
    }

    protected function getFixtureData(): array
    {
        return ['CFD Indexes\UK100'];
    }
}
