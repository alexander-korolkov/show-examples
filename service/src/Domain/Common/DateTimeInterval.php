<?php

namespace Fxtm\CopyTrading\Domain\Common;

final class DateTimeInterval
{
    private $dt1 = null;
    private $dt2 = null;

    public static function fromStrings($dt1, $dt2)
    {
        return new self(DateTime::of($dt1), DateTime::of($dt2));
    }

    public function __construct(DateTime $dt1, DateTime $dt2)
    {
        if ($this->dt1 > $this->dt2) {
            throw new InvalidArgumentException("dt1 > dt2");
        }
        $this->dt1 = $dt1;
        $this->dt2 = $dt2;
    }

    /**
     * @return DateTime
     */
    public function start()
    {
        return $this->dt1;
    }

    /**
     * @return DateTime
     */
    public function end()
    {
        return $this->dt2;
    }

    public function __toString()
    {
        return sprintf("[%s, %s]", $this->dt1, $this->dt2);
    }
}
