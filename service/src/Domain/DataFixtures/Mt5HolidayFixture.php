<?php

namespace Fxtm\CopyTrading\Domain\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Fxtm\CopyTrading\Tests\Entities\Mt5Holiday;

class Mt5HolidayFixture extends BaseFixture
{
    private const MODE = 1;
    private const WORK_FROM = 0;
    private const DEFAULT_REC_OPERATION = 'X';

    private const KEY_SYMBOLS = 'symbols';
    private const KEY_DAY = 'day';
    private const KEY_MONTH = 'month';
    private const KEY_YEAR = 'year';
    private const KEY_WORK_FROM = 'work_from';
    private const KEY_WORK_TO = 'work_to';
    private const KEY_REC_OPERATION = 'rec_operation';

    public function load(ObjectManager $manager)
    {
        foreach ($this->getFixtureData() as $elem) {
            $holiday = new Mt5Holiday();
            $holiday->setFrsServerId(Mt5PositionFixture::TEST_FRS_SERVER_ID);
            $holiday->setFrsRecOperation($elem[self::KEY_REC_OPERATION] ?? self::DEFAULT_REC_OPERATION);
            $holiday->setSymbols($elem[self::KEY_SYMBOLS]);
            $holiday->setMode(self::MODE);
            $holiday->setDay($elem[self::KEY_DAY]);
            $holiday->setMonth($elem[self::KEY_MONTH]);
            $holiday->setYear($elem[self::KEY_YEAR]);
            $holiday->setWorkFrom($elem[self::KEY_WORK_FROM] ?? self::WORK_FROM);
            $holiday->setWorkTo($elem[self::KEY_WORK_TO]);

            $manager->persist($holiday);
        }
        $manager->flush();
    }

    protected function getFixtureData(): array
    {
        return [
            [
                self::KEY_SYMBOLS => '*',
                self::KEY_DAY => date('j', strtotime('+3 days')),
                self::KEY_MONTH => date('n', strtotime('+3 days')),
                self::KEY_YEAR => date('Y', strtotime('+3 days')),
                self::KEY_WORK_TO => 0
            ],
            [
                self::KEY_SYMBOLS => 'Cash Equities\*',
                self::KEY_DAY => date('j', strtotime('+4 days')),
                self::KEY_MONTH => date('n', strtotime('+4 days')),
                self::KEY_YEAR => date('Y', strtotime('+4 days')),
                self::KEY_WORK_TO => 0
            ],
            [
                self::KEY_SYMBOLS => 'CFD Comodts\*',
                self::KEY_DAY => date('j', strtotime('+4 days')),
                self::KEY_MONTH => date('n', strtotime('+4 days')),
                self::KEY_YEAR => date('Y', strtotime('+4 days')),
                self::KEY_WORK_TO => 1184
            ],
            [
                self::KEY_SYMBOLS => 'Spot Metals\*, CFD Indexes\Jap225, CFD Indexes\ND100m, CFD Indexes\SP500m',
                self::KEY_DAY => date('j', strtotime('+4 days')),
                self::KEY_MONTH => date('n', strtotime('+4 days')),
                self::KEY_YEAR => date('Y', strtotime('+4 days')),
                self::KEY_WORK_TO => 1199
            ],
            [
                self::KEY_SYMBOLS => 'Cash Equities\*',
                self::KEY_DAY => date('j', strtotime('+5 days')),
                self::KEY_MONTH => date('n', strtotime('+5 days')),
                self::KEY_YEAR => date('Y', strtotime('+5 days')),
                self::KEY_WORK_TO => 1199
            ],
            [
                self::KEY_SYMBOLS => '!CFD Indexes\Jap225, CFD Indexes\SP500m, CFD Indexes\ND100m',
                self::KEY_DAY => date('j', strtotime('+5 days')),
                self::KEY_MONTH => date('n', strtotime('+5 days')),
                self::KEY_YEAR => date('Y', strtotime('+5 days')),
                self::KEY_WORK_TO => 1214
            ],
            [
                self::KEY_SYMBOLS => 'CFD Comodts\*',
                self::KEY_DAY => date('j', strtotime('+5 days')),
                self::KEY_MONTH => date('n', strtotime('+5 days')),
                self::KEY_YEAR => date('Y', strtotime('+5 days')),
                self::KEY_WORK_TO => 1229
            ],
            [
                self::KEY_SYMBOLS => 'Spot Metals\*',
                self::KEY_DAY => date('j', strtotime('+5 days')),
                self::KEY_MONTH => date('n', strtotime('+5 days')),
                self::KEY_YEAR => date('Y', strtotime('+5 days')),
                self::KEY_WORK_TO => 1244
            ],
            [
                self::KEY_SYMBOLS => 'CFD Indexes\UK100',
                self::KEY_DAY => date('j', strtotime('+6 days')),
                self::KEY_MONTH => date('n', strtotime('+6 days')),
                self::KEY_YEAR => date('Y', strtotime('+6 days')),
                self::KEY_WORK_FROM => 935,
                self::KEY_WORK_TO => 1439,
            ],
            [
                self::KEY_SYMBOLS => 'CFD Indexes\UK100',
                self::KEY_DAY => date('j'),
                self::KEY_MONTH => date('n'),
                self::KEY_YEAR => date('Y'),
                self::KEY_WORK_FROM => 935,
                self::KEY_WORK_TO => 1439,
            ],
            [
                self::KEY_SYMBOLS => 'Complex\Index\*',
                self::KEY_DAY => date('j'),
                self::KEY_MONTH => date('n'),
                self::KEY_YEAR => date('Y'),
                self::KEY_WORK_TO => 1200
            ],
            [
                self::KEY_SYMBOLS => '!Complex\Index\*, *',
                self::KEY_DAY => date('j', strtotime('+1 day')),
                self::KEY_MONTH => date('n', strtotime('+1 day')),
                self::KEY_YEAR => date('Y', strtotime('+1 day')),
                self::KEY_WORK_TO => 1200
            ],
            [
                self::KEY_SYMBOLS => '*',
                self::KEY_DAY => date('j', strtotime('+2 days')),
                self::KEY_MONTH => date('n', strtotime('+2 days')),
                self::KEY_YEAR => date('Y', strtotime('+2 day')),
                self::KEY_WORK_TO => 1200,
                self::KEY_REC_OPERATION => 'D',
            ],
        ];
    }
}
