<?php

namespace Fxtm\CopyTrading\Interfaces\Statistics\Utils;

use DateTimeImmutable;
use DateTimeZone;
use Fxtm\CopyTrading\Interfaces\Repository\EquityRepository;

class FixEquityServiceImpl implements FixEquityService
{
    /**
     * @var EquityRepository
     */
    private $equityRepository;

    /**
     * @param EquityRepository $equityRepository
     */
    public function __construct(EquityRepository $equityRepository)
    {
        $this->equityRepository = $equityRepository;
    }

    /**
     * @inheritDoc
     */
    public function addMissingHourlyEquities(int $accNo, ?DateTimeImmutable $fromDate, ?DateTimeImmutable $toDate, bool $isDryRun): array
    {
        $existingEquities = $this->equityRepository->getEquitiesAsArray($accNo, $fromDate, $toDate);

        $missingEquities = [];
        $lastDateHourBegin = null;
        $lastEquity = null;
        $utcTimezone = new DateTimeZone('UTC'); // to work around Cyprus timezone time shift

        foreach ($existingEquities as $existingEquity) {
            $dateHourBegin = new DateTimeImmutable((new DateTimeImmutable($existingEquity['date_time'], $utcTimezone))->format('Y-m-d H:00:00'), $utcTimezone);
            if (null !== $lastDateHourBegin) {
                $previousDateHourBegin = static::getPreviousDateHourBegin($dateHourBegin);
                while ($previousDateHourBegin > $lastDateHourBegin) {
                    if ($isDryRun) {
                        $id = 0;
                    } else {
                        $entity = $this->equityRepository->add($accNo, new DateTimeImmutable($previousDateHourBegin->format('Y-m-d H:i:01')), $lastEquity, '0');
                        $id = $entity->getId();
                    }
                    $missingEquities[$previousDateHourBegin->format('Y-m-d H:i:01')] = [$id, $lastEquity];
                    $previousDateHourBegin = static::getPreviousDateHourBegin($previousDateHourBegin);
                }
            }
            $lastDateHourBegin = $dateHourBegin;
            $lastEquity = $existingEquity['equity'];
        }
        ksort($missingEquities);
        return $missingEquities;
    }

    private static function getPreviousDateHourBegin(DateTimeImmutable $date)
    {
        $previousDateHourBegin = $date->modify('- 1 hour');
        while(!static::isDateToCollectEquity($previousDateHourBegin)) {
            $previousDateHourBegin = $previousDateHourBegin->modify('- 1 hour');
        }
        return $previousDateHourBegin;
    }

    private static function isDateToCollectEquity(DateTimeImmutable $date)
    {
        $thisMondayDate = $date->modify('monday this week')->modify('+ 1 hour');
        $thisSaturdayDate = $date->modify('saturday this week');
        return $date >= $thisMondayDate && $date <= $thisSaturdayDate;
    }
}
