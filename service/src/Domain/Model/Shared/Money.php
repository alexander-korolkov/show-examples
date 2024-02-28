<?php

namespace Fxtm\CopyTrading\Domain\Model\Shared;

use Fxtm\CopyTrading\Application\Utils\FloatUtils;
use Fxtm\CopyTrading\Domain\Common\ValueObject;

final class Money implements ValueObject
{
    const SCALE = 6;

    /**
     * @var string
     */
    private $amount;

    /**
     * @var Currency
     */
    private $currency;

    public function __construct($amount, Currency $currency)
    {
        $this->amount = FloatUtils::toString($amount, self::SCALE);
        $this->currency = $currency;
    }

    public function amount()
    {
        return floatval($this->amount);
    }

    public function currency()
    {
        return $this->currency;
    }

    public function add(Money $other)
    {
        $this->checkCurrency($other);
        return new Money(
            bcadd($this->amount, $other->amount, self::SCALE),
            $this->currency
        );
    }

    public function subtract(Money $other)
    {
        $this->checkCurrency($other);
        return new Money(
            bcsub($this->amount, $other->amount, self::SCALE),
            $this->currency
        );
    }

    public function multiply($factor)
    {
        return new Money(
            bcmul($this->amount, FloatUtils::toString($factor, self::SCALE), self::SCALE),
            $this->currency
        );
    }

    public function divide($factor)
    {
        return new Money(
            bcdiv($this->amount, FloatUtils::toString($factor, self::SCALE), self::SCALE),
            $this->currency
        );
    }

    public function isGreaterThan(Money $other)
    {
        $this->checkCurrency($other);
        return $this->amount > $other->amount;
    }

    public function isGreaterThanOrEqualTo(Money $other)
    {
        $this->checkCurrency($other);
        return $this->isGreaterThan($other) || $this->isEqualTo($other);
    }

    public function isLessThan(Money $other)
    {
        $this->checkCurrency($other);
        return $this->amount < $other->amount;
    }

    public function isLessThanOrEqualTo(Money $other)
    {
        $this->checkCurrency($other);
        return $this->isLessThan($other) || $this->isEqualTo($other);
    }

    public function isEqualTo(Money $other)
    {
        $this->checkCurrency($other);
        return $this->amount === $other->amount;
    }

    public function isSameValueAs(ValueObject $other)
    {
        if ($other instanceof $this) {
            $amountsEqual = $this->amount === $other->amount;
            $currenciesSame = $this->currency->isSameValueAs($other->currency);
            return $amountsEqual && $currenciesSame;
        }

        return false;
    }

    public function truncate($scale = 2)
    {
        return new Money(
            FloatUtils::truncate($this->amount, $scale),
            $this->currency
        );
    }

    public function __toString()
    {
        return sprintf("%01.2f %s", $this->amount, $this->currency);
    }

    private function checkCurrency(Money $other)
    {
        if (!$this->currency->isSameValueAs($other->currency)) {
            throw new IncompatibleCurrency();
        }
    }
}
