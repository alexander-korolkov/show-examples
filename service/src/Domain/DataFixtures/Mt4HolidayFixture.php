<?php

namespace Fxtm\CopyTrading\Domain\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Fxtm\CopyTrading\Tests\Entities\Mt4Holiday;

class Mt4HolidayFixture extends BaseFixture
{
    private const ENABLE = 1;
    private const WORK_FROM = 0;

    private const KEY_SYMBOLS = 'symbols';
    private const KEY_DAY = 'day';
    private const KEY_MONTH = 'month';
    private const KEY_WORK_FROM = 'work_from';
    private const KEY_WORK_TO = 'work_to';

    public function load(ObjectManager $manager)
    {
        foreach ($this->getFixtureData() as $elem) {
            $holiday = new Mt4Holiday();
            $holiday->setFrsServerId(Mt5PositionFixture::TEST_FRS_SERVER_ID);
            $holiday->setSymbol($elem[self::KEY_SYMBOLS]);
            $holiday->setEnable(self::ENABLE);
            $holiday->setDay($elem[self::KEY_DAY]);
            $holiday->setMonth($elem[self::KEY_MONTH]);
            $holiday->setYear(date('Y'));
            $holiday->setFrom($elem[self::KEY_WORK_FROM] ?? self::WORK_FROM);
            $holiday->setTo($elem[self::KEY_WORK_TO]);

            $manager->persist($holiday);
        }
        $manager->flush();
    }

    protected function getFixtureData(): array
    {
        return [
            [
                self::KEY_SYMBOLS => 'CFD Indexes\UK100',
                self::KEY_DAY => date('j'),
                self::KEY_MONTH => date('n'),
                self::KEY_WORK_FROM => 935,
                self::KEY_WORK_TO => 1439,
            ],
        ];
    }
}
