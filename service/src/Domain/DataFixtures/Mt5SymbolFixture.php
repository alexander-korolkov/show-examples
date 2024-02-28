<?php

namespace Fxtm\CopyTrading\Domain\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Fxtm\CopyTrading\Tests\Entities\Mt5Symbol;

class Mt5SymbolFixture extends BaseFixture
{
    public const SYMBOL_SOME = 'SOME';
    public const SYMBOL_SYMB = 'SYMB';
    public const SYMBOL_INDX = 'INDX';
    public const SYMBOL_SP5 = 'SP5';
    public const SYMBOL_SP2 = 'SP2';
    public const SYMBOL_UK100 = 'UK100';
    public const SYMBOL_UK500 = 'UK500';

    private const KEY_PATH = 'path';
    private const KEY_SYMBOL = 'symbol';

    public function load(ObjectManager $manager)
    {
        foreach ($this->getFixtureData() as $elem) {
            $symbol = new Mt5Symbol();
            $symbol->setFrsServerId(Mt5PositionFixture::TEST_FRS_SERVER_ID);
            $symbol->setSymbol($elem[self::KEY_SYMBOL]);
            $symbol->setPath($elem[self::KEY_PATH]);
            $manager->persist($symbol);
        }
        $manager->flush();
    }

    protected function getFixtureData(): array
    {
        return [
            [
                self::KEY_PATH => 'Cash Equities\\Some',
                self::KEY_SYMBOL => self::SYMBOL_SOME,
            ],
            [
                self::KEY_PATH => 'Wow\\Another',
                self::KEY_SYMBOL => self::SYMBOL_SYMB,
            ],
            [
                self::KEY_PATH => 'Wow\\Another',
                self::KEY_SYMBOL => 'Cash Equities\\*',
            ],
            [
                self::KEY_PATH => 'CFD Indexes\\Jap225',
                self::KEY_SYMBOL => self::SYMBOL_INDX,
            ],
            [
                self::KEY_PATH => 'CFD Indexes\\SP500m',
                self::KEY_SYMBOL => self::SYMBOL_SP5,
            ],
            [
                self::KEY_PATH => 'CFD Indexes\\SP500m',
                self::KEY_SYMBOL => self::SYMBOL_SP2,
            ],
            [
                self::KEY_PATH => 'CFD Indexes\\UK100',
                self::KEY_SYMBOL => self::SYMBOL_UK100,
            ],
            [
                self::KEY_PATH => 'Complex\\Index\\UK500',
                self::KEY_SYMBOL => self::SYMBOL_UK500,
            ],
        ];
    }
}
