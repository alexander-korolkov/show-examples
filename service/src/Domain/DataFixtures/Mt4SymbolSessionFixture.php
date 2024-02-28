<?php

namespace Fxtm\CopyTrading\Domain\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Fxtm\CopyTrading\Tests\Entities\Mt4SymbolSession;

class Mt4SymbolSessionFixture extends BaseFixture
{
    private const KEY_SYMBOL = 'Symbol';
    private const KEY_TYPE = 'Type';
    private const KEY_DAY = 'Day';
    private const KEY_OPEN_HOURS = 'OpenHours';
    private const KEY_OPEN_MINUTES = 'OpenMinutes';
    private const KEY_CLOSE_HOURS = 'CloseHours';
    private const KEY_CLOSE_MINUTES = 'CloseMinutes';

    private const DEFAULT_OPEN_HOURS = 15;
    private const DEFAULT_OPEN_MINUTES = 20;
    private const DEFAULT_CLOSE_HOURS = 23;
    private const DEFAULT_CLOSE_MINUTES = 0;

    public function load(ObjectManager $manager)
    {
        foreach ($this->getFixtureData() as $elem) {
            $symbolSession = new Mt4SymbolSession();
            $symbolSession->setFrsServerId(Mt5PositionFixture::TEST_FRS_SERVER_ID);
            $symbolSession->setSymbol($elem[self::KEY_SYMBOL]);
            $symbolSession->setType($elem[self::KEY_TYPE]);
            $symbolSession->setDay($elem[self::KEY_DAY]);
            $symbolSession->setOpenHour($elem[self::KEY_OPEN_HOURS] ?? self::DEFAULT_OPEN_HOURS);
            $symbolSession->setOpenMin($elem[self::KEY_OPEN_MINUTES] ?? self::DEFAULT_OPEN_MINUTES);
            $symbolSession->setCloseHour($elem[self::KEY_CLOSE_HOURS] ?? self::DEFAULT_CLOSE_HOURS);
            $symbolSession->setCloseMin($elem[self::KEY_CLOSE_MINUTES] ?? self::DEFAULT_CLOSE_MINUTES);
            $manager->persist($symbolSession);
        }
        $manager->flush();
    }

    protected function getFixtureData(): array
    {
        return [
            [
                self::KEY_SYMBOL => Mt5SymbolFixture::SYMBOL_UK100,
                self::KEY_TYPE => 1,
                self::KEY_DAY => 5,
            ],
            [
                self::KEY_SYMBOL => Mt5SymbolFixture::SYMBOL_UK100,
                self::KEY_TYPE => 2,
                self::KEY_DAY => 5,
            ],
            [
                self::KEY_SYMBOL => Mt5SymbolFixture::SYMBOL_UK100,
                self::KEY_TYPE => 1,
                self::KEY_DAY => 1,
            ],
            [
                self::KEY_SYMBOL => Mt5SymbolFixture::SYMBOL_UK100,
                self::KEY_TYPE => 2,
                self::KEY_DAY => 1,
            ],
            [
                self::KEY_SYMBOL => Mt5SymbolFixture::SYMBOL_UK100,
                self::KEY_TYPE => 1,
                self::KEY_DAY => 2,
            ],
            [
                self::KEY_SYMBOL => Mt5SymbolFixture::SYMBOL_UK100,
                self::KEY_TYPE => 2,
                self::KEY_DAY => 2,
            ],
            [
                self::KEY_SYMBOL => Mt5SymbolFixture::SYMBOL_UK100,
                self::KEY_TYPE => 1,
                self::KEY_DAY => 3,
            ],
            [
                self::KEY_SYMBOL => Mt5SymbolFixture::SYMBOL_UK100,
                self::KEY_TYPE => 2,
                self::KEY_DAY => 3,
            ],
        ];
    }
}
