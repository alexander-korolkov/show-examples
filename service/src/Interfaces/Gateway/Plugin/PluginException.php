<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Plugin;

use Fxtm\CopyTrading\Application\GatewayException;

class PluginException extends GatewayException
{
    protected $code = null;
    protected $message = null;

    public function __construct($code, $message = "")
    {
        $this->code = $code;
        $this->message = $message;
    }

    public function __toString()
    {
        return sprintf("Plugin Error [%d: %s]", $this->code, $this->message);
    }
}
