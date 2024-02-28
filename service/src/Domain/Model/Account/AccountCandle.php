<?php

namespace Fxtm\CopyTrading\Domain\Model\Account;

class AccountCandle
{
    /**
     * @var int
     */
    private $login;

    /**
     * @var float
     */
    private $equityClose;

    /**
     * AccountCandle constructor.
     * @param int $login
     * @param float $equityClose
     */
    public function __construct(int $login, float $equityClose)
    {
        $this->login = $login;
        $this->equityClose = $equityClose;
    }

    /**
     * @return float
     */
    public function getEquityClose(): float
    {
        return $this->equityClose;
    }

    /**
     * @param float $equityClose
     */
    public function setEquityClose(float $equityClose): void
    {
        $this->equityClose = $equityClose;
    }

    /**
     * @return int
     */
    public function getLogin(): int
    {
        return $this->login;
    }

    /**
     * @param int $login
     */
    public function setLogin(int $login): void
    {
        $this->login = $login;
    }
}
