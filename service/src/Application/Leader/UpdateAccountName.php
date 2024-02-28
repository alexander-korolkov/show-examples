<?php

namespace Fxtm\CopyTrading\Application\Leader;

class UpdateAccountName
{
    private $accNo = null;
    private $accName  = "";

    public function __construct($accNo, $accName)
    {
        $this->accNo    = $accNo;
        $this->accName  = $accName;
    }

    public function getAccountName()
    {
        return $this->accName;
    }

    public function getAccountNo()
    {
        return $this->accNo;
    }
}
