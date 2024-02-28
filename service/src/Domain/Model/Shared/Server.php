<?php

namespace Fxtm\CopyTrading\Domain\Model\Shared;

use Fxtm\CopyTrading\Domain\Model\Account\AccountType;

final class Server
{
    public const ECN_ZERO      = 1; // MT4 ecn zero @ fxtm
    public const ECN           = 2; // MT4 ecn @ fxtm
    public const AI_ECN        = 3; // MT4 ecn @ aint
    public const MT5_FXTM      = 4; // MT5 ecn + ecn zero @ fxtm
    public const MT5_AINT      = 5; // MT5 ecn @ aint
    public const MT5_AI_ECN    = 6; // MT5 ecn with leaders @ aint
    public const ADVANTAGE_ECN = 7; // MT4 advantage ecn @ fxtm

    private static $servers = [
        self::ECN_ZERO      => "ECN_ZERO",
        self::ECN           => "ECN",
        self::AI_ECN        => "AI_ECN",
        self::MT5_FXTM      => "MT5_FXTM",
        self::MT5_AINT      => "MT5_AINT",
        self::MT5_AI_ECN    => "MT5_AI_ECN",
        self::ADVANTAGE_ECN => "ADVANTAGE_ECN",
    ];

    public static function toString($server)
    {
        if (isset(self::$servers[$server])) {
            return self::$servers[$server];
        }
        throw new \Exception("Unknown server: {$server}");
    }

    /**
     * @return array
     */
    public static function list()
    {
        return [
            self::ECN_ZERO,
            self::ECN,
            self::AI_ECN,
            self::MT5_FXTM,
            self::MT5_AINT,
            self::MT5_AI_ECN,
            self::ADVANTAGE_ECN,
        ];
    }

    public static function mt4Servers() : array
    {
        return [
            self::ECN_ZERO,
            self::ECN,
            self::AI_ECN,
            self::ADVANTAGE_ECN,
        ];
    }

    /**
     * Returns true id the given server
     * contains leader accounts
     *
     * @param $server
     * @return bool
     */
    public static function containsLeaders($server)
    {
        return in_array($server, [
            self::ECN,
            self::ECN_ZERO,
            self::AI_ECN,
            self::MT5_AI_ECN,
            self::ADVANTAGE_ECN,
            self::MT5_AI_ECN,
        ]);
    }

    /**
     * Returns server by given account type
     *
     * @param int $accountType
     * @return int
     */
    public static function byAccountType($accountType)
    {
        $serversByAccountType = [
            AccountType::LEADER_FXTM_ECN_MT4 => self::ECN,
            AccountType::AGGREGATE_FXTM_ECN_MT4 => self::MT5_FXTM,
            AccountType::FOLLOWER_FXTM_ECN_MT4 => self::MT5_FXTM,
            AccountType::FOLLOWER_AINT_TO_FXTM_ECN_MT4 => self::MT5_FXTM,

            AccountType::LEADER_FXTM_ECN_ZERO_MT4 => self::ECN_ZERO,
            AccountType::AGGREGATE_FXTM_ECN_ZERO_MT4 => self::MT5_FXTM,
            AccountType::FOLLOWER_FXTM_ECN_ZERO_MT4 => self::MT5_FXTM,
            AccountType::FOLLOWER_AINT_TO_FXTM_ECN_ZERO_MT4 => self::MT5_FXTM,

            AccountType::LEADER_FXTM_ADVANTAGE_ECN_MT4 => self::ADVANTAGE_ECN,
            AccountType::AGGREGATE_FXTM_ADVANTAGE_ECN_MT4 => self::MT5_FXTM,
            AccountType::FOLLOWER_FXTM_ADVANTAGE_ECN_MT4 => self::MT5_FXTM,
            AccountType::FOLLOWER_AINT_TO_FXTM_ADVANTAGE_ECN_MT4 => self::MT5_FXTM,

            AccountType::LEADER_AINT_ECN_MT4 => self::AI_ECN,
            AccountType::AGGREGATE_AINT_ECN_MT4 => self::MT5_AINT,
            AccountType::FOLLOWER_AINT_ECN_MT4 => self::MT5_AINT,
            AccountType::FOLLOWER_FXTM_TO_AINT_ECN_MT4 => self::MT5_AINT,

            AccountType::LEADER_AINT_ECN_MT5 => self::MT5_AI_ECN,
            AccountType::AGGREGATE_AINT_ECN_MT5 => self::MT5_AINT,
            AccountType::FOLLOWER_AINT_ECN_MT5 => self::MT5_AINT,
            AccountType::FOLLOWER_FXTM_TO_AINT_ECN_MT5 => self::MT5_AINT,

            AccountType::LEADER_ABY_ECN_MT4 => self::AI_ECN,
            AccountType::AGGREGATE_ABY_ECN_MT4 => self::MT5_AINT,
            AccountType::FOLLOWER_ABY_ECN_MT4 => self::MT5_AINT,

            AccountType::LEADER_ABY_ECN_ZERO_MT4 => self::AI_ECN,
            AccountType::AGGREGATE_ABY_ECN_ZERO_MT4 => self::MT5_AINT,
            AccountType::FOLLOWER_ABY_ECN_ZERO_MT4 => self::MT5_AINT,
        ];

        return $serversByAccountType[$accountType];
    }

    public const PLATFORM_TYPE_MT4 = 'mt4';
    public const PLATFORM_TYPE_MT5 = 'mt5';

    private static $serverPlatformTypes = [
        Server::ECN_ZERO => self::PLATFORM_TYPE_MT4,
        Server::ECN => self::PLATFORM_TYPE_MT4,
        Server::AI_ECN => self::PLATFORM_TYPE_MT4,
        Server::MT5_FXTM => self::PLATFORM_TYPE_MT5,
        Server::MT5_AINT => self::PLATFORM_TYPE_MT5,
        Server::MT5_AI_ECN => self::PLATFORM_TYPE_MT5,
        Server::ADVANTAGE_ECN => self::PLATFORM_TYPE_MT4,
    ];

    public static function GetPlatformType(int $server) {
        if (empty(self::$serverPlatformTypes[$server])) {
            throw new \RuntimeException("Undefined server ID : '$server'");
        }

        return self::$serverPlatformTypes[$server];
    }
}
