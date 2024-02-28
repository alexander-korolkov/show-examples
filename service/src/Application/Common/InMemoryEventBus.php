<?php

namespace Fxtm\CopyTrading\Application\Common;

use Fxtm\CopyTrading\Domain\Common\Event;
use Traversable;

class InMemoryEventBus implements EventBus
{
    private $callbacks = array();

    public function subscribe($eventType, $callback)
    {
        $this->callbacks[$eventType][] = $callback;
    }

    public function subscribeToAll($callback)
    {
        foreach ($this->callbacks as &$callbacks) {
            $callbacks[] = $callback;
        }
    }

    public function publish(Event $event)
    {
        if (!empty($this->callbacks[$event::type()])) {
            foreach ($this->callbacks[$event::type()] as $callback) {
                call_user_func($callback, $event);
            }
        }
    }

    public function publishAll(Traversable $events)
    {
        foreach ($events as $event) {
            $this->publish($event);
        }
    }
}
