<?php

namespace Fxtm\CopyTrading\Domain\Common;

final class DateTime extends \DateTime
{
    const FORMAT = "Y-m-d H:i:s";

    /**
     *
     * @return DateTime
     */
    public static function of($moment)
    {
        return new self($moment);
    }

    /**
     *
     * @return DateTime
     */
    public static function NOW()
    {
        return self::of("now");
    }

    public function isWeekend()
    {
        return in_array($this->getWeekdayNumber(), [6, 7]);
    }

    /**
     * @param DateTime $start
     * @return int
     */
    public function countWeekends(DateTime $start)
    {
        $sum = 0;

        switch ($start->getWeekdayNumber()) {
            case 6:
                $start->modify('+2 days');
                $sum += 2;
                break;
            case 7:
                $start->modify('+1 day');
                $sum += 1;
                break;
            default:
                // Set date to beginning of the week.
                $start->modify('-' . ($start->getWeekdayNumber() - 1) .' day' );
        }

        switch ($this->getWeekdayNumber()) {
            case 6:
                $this->modify('-5 days');
                $sum += 1;
                break;
            case 7:
                $this->modify('-6 days');
                $sum += 2;
                break;
        }

        return  intdiv($this->diff($start)->days , 7) * 2 + $sum;
    }

    public function getWeekdayNumber()
    {
        return date("N", $this->getTimestamp());
    }

    /**
     * @param string $relDtStr Relative datetime string
     * @return DateTime new instance
     */
    public function relativeDatetime($relDtStr)
    {
        $dt = clone $this;
        return $dt->modify($relDtStr);
    }

    /**
     * @param string $time
     * @return DateTime new instance
     */
    public function withTime($time)
    {
        $dt = clone $this;
        list($h, $m, $s) = explode(':', $time);
        $dt->setTime(intval($h), intval($m), intval($s));
        return $dt;
    }

    /**
     *
     * @return DateTime new instance
     */
    public function nextMonth()
    {
        $year  = $this->getYear();
        $month = $this->getMonth() + 1;
        if ($month > 12) {
            $month = 1;
            $year += 1;
        }

        $dt = clone $this;
        $dt->setDate($year, $month, 1)->setTime(0, 0, 0);
        return $dt;
    }

    /**
     *
     * @return DateTime new instance
     */
    public function prevMonth()
    {
        $year  = $this->getYear();
        $month = $this->getMonth() - 1;
        if ($month < 1) {
            $month = 12;
            $year -= 1;
        }

        $dt = clone $this;
        $dt->setDate($year, $month, 1)->setTime(0, 0, 0);
        return $dt;
    }

    /**
     *
     * @return DateTime new instance
     */
    public function nextDay()
    {
        $year  = $this->getYear();
        $month = $this->getMonth();
        $day   = $this->getDay() + 1;

        // workaround to release the requirement of cal_days_in_month function
        $daysInMonth = (int) (new self("{$year}-{$month}-01"))->modify('next month - 1 day')->format('d');

        if ($day > $daysInMonth) {
            $day = 1;
            $month += 1;
            if ($month > 12) {
                $month = 1;
                $year += 1;
            }
        }

        $dt = clone $this;
        $dt->setDate($year, $month, $day)->setTime(0, 0, 0);
        return $dt;
    }

    /**
     *
     * @return DateTime new instance
     */
    public function currDay()
    {
        $dt = clone $this;
        $dt->setTime(0, 0, 0);
        return $dt;
    }

    /**
     *
     * @return DateTime new instance
     */
    public function prevDay()
    {
        $year  = $this->getYear();
        $month = $this->getMonth();
        $day   = $this->getDay() - 1;

        if ($day < 1) {
            $month -= 1;
            if ($month < 1) {
                $month = 12;
                $year -= 1;
            }
            $day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        }

        $dt = clone $this;
        $dt->setDate($year, $month, $day)->setTime(0, 0, 0);
        return $dt;
    }

    /**
     *
     * @return DateTime new instance
     */
    public function prevHour()
    {
        $year   = $this->getYear();
        $month  = $this->getMonth();
        $day    = $this->getDay();
        $hour   = $this->getHour() - 1;

        if ($hour < 0) {
            $hour = 23;
            $day -= 1;
            if ($day < 1) {
                $month -= 1;
                if ($month < 0) {
                    $month = 12;
                    $year -= 1;
                }
                $day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            }
        }

        $dt = clone $this;
        $dt->setDate($year, $month, $day)->setTime($hour, 0, 0);
        return $dt;
    }

    /**
     *
     * @return DateTime new instance
     */
    public function currHour()
    {
        $hour = $this->getHour();

        $dt = clone $this;
        $dt->setTime($hour, 0, 0);
        return $dt;
    }

    /**
     *
     * @return DateTime new instance
     */
    public function nextHour()
    {
        $year   = $this->getYear();
        $month  = $this->getMonth();
        $day    = $this->getDay();
        $hour   = $this->getHour() + 1;

        if ($hour > 23) {
            $hour = 0;
            $day += 1;
            if ($day > cal_days_in_month(CAL_GREGORIAN, $month, $year)) {
                $day = 1;
                $month += 1;
                if ($month > 12) {
                    $month = 1;
                    $year += 1;
                }
            }
        }

        $dt = clone $this;
        $dt->setDate($year, $month, $day)->setTime($hour, 0, 0);
        return $dt;
    }

    public function getYear()
    {
        return intval($this->format("Y"));
    }

    public function getMonth()
    {
        return intval($this->format("m"));
    }

    public function getDay()
    {
        return intval($this->format("d"));
    }

    public function getHour()
    {
        return intval($this->format("H"));
    }

    public function getMinute()
    {
        return intval($this->format("i"));
    }

    public function getSecond()
    {
        return intval($this->format("s"));
    }

    public function __toString()
    {
        return $this->format(self::FORMAT);
    }
}
