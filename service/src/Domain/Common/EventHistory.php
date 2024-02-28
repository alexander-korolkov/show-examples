<?php

namespace Fxtm\CopyTrading\Domain\Common;

use Countable;
use Iterator;

class EventHistory implements Iterator, Countable
{
    private $events = array();
    private $position = 0;

    public function __construct(array $events)
    {
        $this->events = $events;
    }

    public function lastEvent()
    {
        return $this->events[sizeof($this->events) - 1];
    }

    public function count()
    {
        return count($this->events);
    }

    public function current()
    {
        return $this->events[$this->position];
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        return ++$this->position;
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function valid()
    {
        return isset($this->events[$this->position]);
    }

}
