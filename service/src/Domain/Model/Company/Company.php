<?php

namespace Fxtm\CopyTrading\Domain\Model\Company;

class Company
{
    private $id;

    const ID_EU = 1; // ForexTime Limited
    const ID_GLOBAL = 2;
    const ID_FT_GLOBAL = 3;
    const ID_UK = 4;
    const ID_ALPARI = 50;
    const ID_ABY = 51;

    /**
     * Company constructor.
     * @param $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * @return bool
     */
    public function isEu()
    {
        return $this->id == self::ID_EU;
    }

    /**
     * @return bool
     */
    public function isAby()
    {
        return $this->id == self::ID_ABY;
    }

    /**
     * @return bool
     */
    public function isUk()
    {
        return $this->id == self::ID_UK;
    }

    /**
     * @return bool
     */
    public function isRegulated()
    {
        return $this->isEu() || $this->isUk();
    }

    /**
     * @return bool
     */
    public function isFt()
    {
        return $this->id == self::ID_FT_GLOBAL;
    }

    /**
     * @return bool
     */
    public function isAlpari()
    {
        return $this->id == self::ID_ALPARI;
    }
}
