<?php

namespace Fxtm\CopyTrading\Application\Common\Scheduler;

use InvalidArgumentException;

final class TimeInterval
{
    private $t1 = null;
    private $t2 = null;

    public static function fromStrings($t1, $t2)
    {
        return new self(Time::fromString($t1), Time::fromString($t2));
    }

    public static function fromIntegers($t1, $t2)
    {
        return new self(Time::fromInteger($t1), Time::fromInteger($t2));
    }

    public function __construct(Time $t1, Time $t2)
    {
        if ($this->t1 > $this->t2) {
            throw new InvalidArgumentException("t1 > t2");
        }
        $this->t1 = $t1;
        $this->t2 = $t2;
    }

    /**
     * @return Time
     */
    public function start()
    {
        return $this->t1;
    }

    /**
     * @return Time
     */
    public function end()
    {
        return $this->t2;
    }

    /**
     * @param TimeInterval $that
     * @return boolean
     */
    public function abuts(TimeInterval $that)
    {
        return $this->t1 == $that->t2 || $this->t2 == $that->t1;
    }

    /**
     * @param TimeInterval $that
     * @return boolean
     */
    public function isAdjacentTo(TimeInterval $that)
    {
        return abs($this->t1->__toInteger() - $that->t2->__toInteger()) == 1 || abs($this->t2->__toInteger() - $that->t1->__toInteger()) == 1;
    }

    /**
     * @param TimeInterval $that
     * @return boolean
     */
    public function overlaps(TimeInterval $that)
    {
        return $this->t1 <= $that->t2 && $this->t2 >= $that->t1;
    }

    /**
     * @param TimeInterval $that
     * @return boolean
     */
    public function contains(TimeInterval $that)
    {
        return $this->t1 <= $that->t1 && $this->t2 >= $that->t2;
    }

    /**
     * @param TimeInterval $that
     * @return TimeInterval
     */
    public function overlap(TimeInterval $that)
    {
        if (!$this->overlaps($that)) {
            return null;
        }
        return new self(max($this->t1, $that->t1), min($this->t2, $that->t2));
    }

    /**
     * @param TimeInterval $that
     * @return TimeInterval
     */
    public function combine(TimeInterval $that)
    {
        if (!$this->overlaps($that) && !$this->abuts($that) && !$this->isAdjacentTo($that)) {
            throw new InvalidArgumentException("Intervals have a gap");
        }
        return new self(min($this->t1, $that->t1), max($this->t2, $that->t2));
    }

    /**
     * @param TimeInterval $that
     * @return array
     * @throws InvalidArgumentException
     */
    public function split(TimeInterval $that)
    {
        if (!$this->contains($that)) {
            throw new InvalidArgumentException("Separator out of range");
        }

        return [
            $this->t1 < $that->t1 ? new self($this->t1, $that->t1->prevSecond()) : null,
            $this->t2 > $that->t2 ? new self($that->t2->nextSecond(), $this->t2) : null,
        ];
    }

    public function __toString()
    {
        return sprintf("[%s, %s]", $this->t1, $this->t2);
    }
}
