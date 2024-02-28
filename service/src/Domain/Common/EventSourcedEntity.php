<?php

namespace Fxtm\CopyTrading\Domain\Common;

abstract class EventSourcedEntity extends AbstractEntity
{
    /**
     * @var EventHistory[]
     */
    private $recentEvents = array();

    public static function buildFromHistory(EventHistory $history)
    {
        $entityType = get_called_class();
        $entity = unserialize(sprintf('O:%d:"%s":0:{}', strlen($entityType), $entityType));
        foreach ($history as $event) {
            $entity->apply($event);
        }
        return $entity;
    }

    public function history() : EventHistory
    {
        return new EventHistory($this->recentEvents);
    }

    public function disposeHistory() : void
    {
        $this->recentEvents = [];
    }

    protected function apply(Event $event) : void
    {
        $eventType = get_class($event);
        $eventOccurred = substr($eventType, strrpos($eventType, '\\') + 1);
        call_user_func(array($this, "when{$eventOccurred}"), $event);
        $this->recentEvents[] = $event;
    }

}
