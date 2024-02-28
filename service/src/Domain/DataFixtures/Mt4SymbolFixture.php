<?php

namespace Fxtm\CopyTrading\Domain\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Fxtm\CopyTrading\Tests\Entities\Mt4Symbol;

class Mt4SymbolFixture extends BaseFixture
{
    public const SYMBOL_SOME = 'SOME';
    public const SYMBOL_SYMB = 'SYMB';
    public const SYMBOL_INDX = 'INDX';
    public const SYMBOL_SP5 = 'SP5';
    public const SYMBOL_SP2 = 'SP2';
    public const SYMBOL_UK100 = 'UK100';

    private const KEY_SYMBOL = 'symbol';

    public function load(ObjectManager $manager)
    {
        foreach ($this->getFixtureData() as $elem) {
            $symbol = new Mt4Symbol();
            $symbol->setFrsServerId(Mt5PositionFixture::TEST_FRS_SERVER_ID);
            $symbol->setSymbol($elem[self::KEY_SYMBOL]);
            $symbol->setType(self::TEST_MT4_SYMBOL_TYPE);
            $manager->persist($symbol);
        }
        $manager->flush();
    }

    protected function getFixtureData(): array
    {
        return [
            [
                self::KEY_SYMBOL => self::SYMBOL_SOME,
            ],
            [
                self::KEY_SYMBOL => self::SYMBOL_SYMB,
            ],
            [
                self::KEY_SYMBOL => 'Cash Equities\\*',
            ],
            [
                self::KEY_SYMBOL => self::SYMBOL_INDX,
            ],
            [
                self::KEY_SYMBOL => self::SYMBOL_SP5,
            ],
            [
                self::KEY_SYMBOL => self::SYMBOL_SP2,
            ],
            [
                self::KEY_SYMBOL => self::SYMBOL_UK100,
            ],
        ];
    }
}
