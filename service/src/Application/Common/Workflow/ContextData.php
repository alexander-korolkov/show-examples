<?php

namespace Fxtm\CopyTrading\Application\Common\Workflow;

class ContextData
{
    public const KEY_BROKER = 'broker';
    public const KEY_ACC_NO = 'accNo';
    public const KEY_PLUGIN_MESSAGE_ID = 'msgId';
    public const KEY_PLUGIN_SERVER_ID = 'server';
    public const REASON = 'reason';

    private $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function get($key)
    {
        if (!$this->has($key)) {
            throw new InvalidContext("Trying to access non-existing '{$key}' variable");
        }
        return $this->data[$key];
    }

    public function getIfHas($key)
    {
        return $this->has($key) ? $this->get($key) : null;
    }

    public function has($key)
    {
        return isset($this->data[$key]);
    }

    public function remove($key)
    {
        unset($this->data[$key]);
    }

    public function toArray()
    {
        return $this->data;
    }

    public function fromArray(array $array)
    {
        $this->data = $array;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }
}
