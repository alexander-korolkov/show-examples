<?php

namespace Fxtm\CopyTrading\Application\Common\Scheduler;

final class Time
{
    private $h = 0;
    private $m = 0;
    private $s = 0;

    public static function fromString($t)
    {
        list($h, $m, $s) = explode(':', $t);
        return new self(intval($h), intval($m), intval($s));
    }

    public static function fromInteger($t)
    {
        list($h, $m, $s) = str_split(sprintf("%'.06d", $t), 2);
        return new self(intval($h), intval($m), intval($s));
    }

    public function __construct($h = 0, $m = 0, $s = 0)
    {
        $this->h = $h;
        $this->m = $m;
        $this->s = $s;
    }

    public function plusMinutes($m)
    {
        return new self(($this->h + ($this->m + $m) / 60) % 24, ($this->m + $m) % 60, $this->s);
    }

    public function prevSecond()
    {
        list($h, $m, $s) = [$this->h, $this->m, $this->s];
        $s--;
        if ($s < 0) {
            $s = 59;
            $m--;
            if ($m < 0) {
                $m = 59;
                $h--;
                if ($h < 0) {
                    $h = 23;
                }
            }
        }
        return new self($h, $m, $s);
    }

    public function nextSecond()
    {
        list($h, $m, $s) = [$this->h, $this->m, $this->s];
        $s++;
        if ($s > 59) {
            $s = 0;
            $m++;
            if ($m > 59) {
                $m = 0;
                $h++;
                if ($h > 23) {
                    $h = 0;
                }
            }
        }
        return new self($h, $m, $s);
    }

    public function __toInteger()
    {
        return intval(sprintf("%'.02d%'.02d%'.02d", $this->h, $this->m, $this->s));
    }

    public function __toString()
    {
        return sprintf("%'.02d:%'.02d:%'.02d", $this->h, $this->m, $this->s);
    }
}
