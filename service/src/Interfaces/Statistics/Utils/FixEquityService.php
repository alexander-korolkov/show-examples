<?php

namespace Fxtm\CopyTrading\Interfaces\Statistics\Utils;

use DateTimeImmutable;

interface FixEquityService
{
    /**
     * Adds missing hourly equities if any
     *
     * @param int $accNo
     * @param DateTimeImmutable|null $fromDate
     * @param DateTimeImmutable|null $toDate
     * @param bool $isDryRun
     * @return array Data of added entities
     */
    public function addMissingHourlyEquities(int $accNo, ?DateTimeImmutable $fromDate, ?DateTimeImmutable $toDate, bool $isDryRun): array;
}
