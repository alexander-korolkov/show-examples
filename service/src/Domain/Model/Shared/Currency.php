<?php

namespace Fxtm\CopyTrading\Domain\Model\Shared;

use Fxtm\CopyTrading\Domain\Common\ValueObject;

final class Currency implements ValueObject
{
    private $code = null;

    private function __construct($code)
    {
        $this->code = $code;
    }

    /**
     * @param $code
     * @return Currency
     */
    public static function forCode($code)
    {
        if (!method_exists(get_class(), $code)) {
            throw new DomainException("Unsupported currency '{$code}'");
        }
        return self::$code();
    }

    public static function USD()
    {
        return new Currency("USD");
    }

    public static function EUR()
    {
        return new Currency("EUR");
    }

    public static function GBP()
    {
        return new Currency("GBP");
    }

    public static function NGN()
    {
        throw new \Exception('Wrong currency NGN');
    }

    public function code()
    {
        return $this->code;
    }

    public function __toString()
    {
        return $this->code;
    }

    public function isSameValueAs(ValueObject $other)
    {
        if ($other instanceof $this) {
            return $this->code === $other->code;
        }

        return false;
    }
}
