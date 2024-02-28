<?php

namespace Fxtm\CopyTrading\Domain\Entity;

class Broker
{
    public const FXTM = 'fxtm';
    public const ALPARI = 'aint';
    public const ABY = 'aby';

    /**
     * Returns array of available brokers
     *
     * @return array
     */
    public static function list(): array
    {
        return [
            Broker::FXTM,
            Broker::ALPARI,
            Broker::ABY,
        ];
    }

    /**
     * Return array of independent brokers
     * (ABY depends from AINT, they has the same my and sas dbs)
     *
     * @return array
     */
    public static function listOfIndependent(): array
    {
        return [
            Broker::FXTM,
            Broker::ALPARI,
        ];
    }
}
