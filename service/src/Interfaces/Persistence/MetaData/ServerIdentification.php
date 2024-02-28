<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\MetaData;


use Fxtm\CopyTrading\Domain\Entity\Broker;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;

class ServerIdentification
{

    const MT_4          = 4;
    const MT_5          = 5;

    /**
     * @var string
     */
    private $broker;

    /**
     * @var int
     */
    private $tradingPlatformVersion;

    /**
     * @var int
     */
    private $serverId;

    /**
     * @var boolean
     */
    private $isFollower;

    /**
     * @var boolean
     */
    private $isAggregator;


    public static function classify(int $accountNumber): ServerIdentification
    {
        // Range are approved here https://docs.gotmy.app/pages/viewpage.action?pageId=117211789&pageId=117211789
        switch (true) {

            //FXTM ECN-Zero Followers
            case $accountNumber >= 56200000 && $accountNumber <= 56999999: //LTD
            case $accountNumber >= 53200000 && $accountNumber <= 53999999: //FTG
            case $accountNumber >= 199200000 && $accountNumber <= 199999999: //FT Kenya
                return new ServerIdentification(Broker::FXTM, Server::ECN_ZERO, self::MT_5, true);

            //FXTM ECN-Zero Aggregators
            case $accountNumber >= 56000000 && $accountNumber <= 56199999: //LTD
            case $accountNumber >= 53000000 && $accountNumber <= 53199999: //FTG
            case $accountNumber >= 199000000 && $accountNumber <= 199199999: //FT Kenya
                return new ServerIdentification(Broker::FXTM, Server::ECN_ZERO, self::MT_5, false, true);

            //FXTM Advantage ECN Leaders
            case $accountNumber >= 141000000 && $accountNumber <= 141499999: //FTG
            case $accountNumber >= 182000000 && $accountNumber <= 182999999: //FT Kenya
                return new ServerIdentification(Broker::FXTM, Server::ADVANTAGE_ECN, self::MT_4);

            //FXTM ECN Followers [migrated from mt4 to mt5]
            case $accountNumber >= 36200000 && $accountNumber <= 36999999:
                return new ServerIdentification(Broker::FXTM, Server::ECN, self::MT_4, true);

            //FXTM ECN Aggregators [migrated from mt4 to mt5]
            case $accountNumber >= 36000000 && $accountNumber <= 36199999:
                return new ServerIdentification(Broker::FXTM, Server::ECN, self::MT_4, false, true);

            //FXTM AI-ECN MT5 Followers [cross-broker]
            case $accountNumber >= 108200000 && $accountNumber <= 108399999: //FTG
            case $accountNumber >= 202000000 && $accountNumber <= 202999999: //FT Kenya
                return new ServerIdentification(Broker::FXTM, Server::ECN, self::MT_5, true);

            //FXTM ECN Followers
            case $accountNumber >= 26200000 && $accountNumber <= 26999999: //LTD
            case $accountNumber >= 25200000 && $accountNumber <= 25999999: //FTG
            case $accountNumber >= 198200000 && $accountNumber <= 198999999: //FT Kenya
                return new ServerIdentification(Broker::FXTM, Server::ECN, self::MT_5, true);

            //FXTM ECN Aggregators
            case $accountNumber >= 26000000 && $accountNumber <= 26199999: //LTD
            case $accountNumber >= 25000000 && $accountNumber <= 25199999: //FTG
            case $accountNumber >= 198000000 && $accountNumber <= 198199999: //FT Kenya
                return new ServerIdentification(Broker::FXTM, Server::ECN, self::MT_5, false, true);

            //FXTM ECN-Zero Leaders
            case $accountNumber >= 9000000 && $accountNumber <= 9499999: //LTD
            case $accountNumber >= 2000000 && $accountNumber <= 2499999: //FTG
            case $accountNumber >= 184000000 && $accountNumber <= 184999999: //FT Kenya
                return new ServerIdentification(Broker::FXTM, Server::ECN_ZERO, self::MT_4);

            //FXTM ECN-Zero Followers [migrated from mt4 to mt5]
            case $accountNumber >= 4100000 && $accountNumber <= 4399999: //LTD
            case $accountNumber >= 1700000 && $accountNumber <= 1999999: //FTG
                return new ServerIdentification(Broker::FXTM, Server::ECN_ZERO, self::MT_4, true);

            //FXTM ECN-Zero Aggregators [migrated from mt4 to mt5]
            case $accountNumber >= 3910000 && $accountNumber <= 4099999: //LTD
            case $accountNumber >= 1510000 && $accountNumber <= 1699999: //FTG
                return new ServerIdentification(Broker::FXTM, Server::ECN_ZERO, self::MT_4, false, true);

            //FXTM ECN Leaders
            case $accountNumber >= 3600000 && $accountNumber <= 3899999: //LTD
            case $accountNumber >= 1000000 && $accountNumber <= 1499999: //FTG
                return new ServerIdentification(Broker::FXTM, Server::ECN, self::MT_4);

            //AINT ECN MT5 Followers
            case $accountNumber >= 107200000 && $accountNumber <= 107399999:
                return new ServerIdentification(Broker::ALPARI, Server::ECN, self::MT_5, true);

            //AINT ECN MT5 Aggregators
            case $accountNumber >= 107000000 && $accountNumber <= 107049999:
                return new ServerIdentification(Broker::ALPARI, Server::ECN, self::MT_5, false, true);

            //AINT ECN MT5 Leaders
            case $accountNumber >= 101000000 && $accountNumber <= 101249999:
            case $accountNumber >= 110000000 && $accountNumber <= 110999999: //Demo interval for staging only
                return new ServerIdentification(Broker::ALPARI, Server::ECN, self::MT_5);

            //FXTM AI-ECN MT4 Followers [cross-broker]
            case $accountNumber >= 88200000 && $accountNumber <= 88399999: //FTG
            case $accountNumber >= 201000000 && $accountNumber <= 201999999: //FT Kenya
                return new ServerIdentification(Broker::ALPARI, Server::ECN, self::MT_4, true);

            //AINT ECN MT4 Followers
            case $accountNumber >= 87200000 && $accountNumber <= 87399999:
                return new ServerIdentification(Broker::ALPARI, Server::ECN, self::MT_4, true);

            //AINT ECN MT4 Followers [migrated from mt4 to mt5]
            case $accountNumber >= 27000000 && $accountNumber <= 27199999:
                return new ServerIdentification(Broker::ALPARI, Server::ECN, self::MT_4, true);

            //AINT ECN MT4 Aggregators [migrated from mt4 to mt5]
            case $accountNumber >= 87000000 && $accountNumber <= 87049999:
                return new ServerIdentification(Broker::ALPARI, Server::ECN, self::MT_4, false, true);

            //AINT ECN MT4 Leaders
            case $accountNumber >= 81000000 && $accountNumber <= 81249999:
            case $accountNumber >= 90000000 && $accountNumber <= 90999999: //Demo interval for staging only
            case $accountNumber >= 3500000 && $accountNumber <= 3599999: //inherited
                return new ServerIdentification(Broker::ALPARI, Server::ECN, self::MT_4);

            //AINT FT-ECN-Zero Followers [migrated from mt4 to mt5]
            case $accountNumber >= 28000000 && $accountNumber <= 28199999:
                return new ServerIdentification(Broker::ALPARI, Server::ECN_ZERO, self::MT_4, true);

            //ABY ECN MT4 Leader
            case $accountNumber >= 91000000 && $accountNumber <= 91099999:
                return new ServerIdentification(Broker::ABY, Server::ECN, self::MT_4);

            //ABY ECN MT4 Aggregators
            case $accountNumber >= 147000000 && $accountNumber <= 147049999:
                return new ServerIdentification(Broker::ABY, Server::ECN, self::MT_5, false, true);

            //ABY ECN MT4 Followers
            case $accountNumber >= 147200000 && $accountNumber <= 147399999:
                return new ServerIdentification(Broker::ABY, Server::ECN, self::MT_5, true);

            //ABY ECN-Zero MT4 Leaders
            case $accountNumber >= 157000000 && $accountNumber <= 157999999:
                return new ServerIdentification(Broker::ABY, Server::ECN_ZERO, self::MT_4);

            //ABY ECN-Zero MT4 Aggregators
            case $accountNumber >= 148000000 && $accountNumber <= 148049999:
                return new ServerIdentification(Broker::ABY, Server::ECN_ZERO, self::MT_5, false, true);

            //ABY ECN-Zero MT4 Followers
            case $accountNumber >= 148200000 && $accountNumber <= 148399999:
                return new ServerIdentification(Broker::ABY, Server::ECN_ZERO, self::MT_5, true);

        }

        return new ServerIdentification(-1, -1, -1);
    }

    public static function legalEntityByName(string $name): int
    {
        return 0;
    }

    private function __construct(
        string $broker,
        int $serverId,
        int $tradingPlatformVersion,
        bool $isFollower = false,
        bool $isAggregator = false
    )
    {
        $this->broker                   = $broker;
        $this->tradingPlatformVersion   = $tradingPlatformVersion;
        $this->serverId                 = $serverId;
        $this->isFollower               = $isFollower;
        $this->isAggregator             = $isAggregator;
    }

    /**
     * @return string
     */
    public function getBroker(): string
    {
        return $this->broker;
    }

    /**
     * @return int
     */
    public function getTradingPlatformVersion(): int
    {
        return $this->tradingPlatformVersion;
    }

    /**
     * @return int
     */
    public function getServerId(): int
    {
        return $this->serverId;
    }

    /**
     * @return bool
     */
    public function isFollower(): bool
    {
        return $this->isFollower;
    }

    /**
     * @return bool
     */
    public function isAggregator(): bool
    {
        return $this->isAggregator;
    }

}