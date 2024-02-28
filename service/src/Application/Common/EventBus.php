<?php

namespace Fxtm\CopyTrading\Application\Common;

use Fxtm\CopyTrading\Domain\Common\Event;
use Traversable;

interface EventBus
{
    public function subscribe($eventType, $callback);
    public function subscribeToAll($callback);
    public function publish(Event $event);
    public function publishAll(Traversable $events);
}
