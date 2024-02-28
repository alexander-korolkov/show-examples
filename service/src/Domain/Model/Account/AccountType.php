<?php

namespace Fxtm\CopyTrading\Domain\Model\Account;

use Fxtm\CopyTrading\Domain\Entity\Broker;

class AccountType
{
    //FXTM ECN MT4
    //Aggregate and follower account types are MT5
    public const LEADER_FXTM_ECN_MT4 = 3;
    public const AGGREGATE_FXTM_ECN_MT4 = 47;
    public const FOLLOWER_FXTM_ECN_MT4 = 44;
    public const FOLLOWER_AINT_TO_FXTM_ECN_MT4 = 10020;


    //FXTM ECN ZERO MT4
    //Aggregate and follower account types are MT5
    public const LEADER_FXTM_ECN_ZERO_MT4 = 23;
    public const AGGREGATE_FXTM_ECN_ZERO_MT4 = 48;
    public const FOLLOWER_FXTM_ECN_ZERO_MT4 = 45;
    public const FOLLOWER_AINT_TO_FXTM_ECN_ZERO_MT4 = 10021;


    //FXTM ADVANTAGE ECN MT4
    //Aggregate and follower account types are MT5
    public const LEADER_FXTM_ADVANTAGE_ECN_MT4 = 50;
    public const AGGREGATE_FXTM_ADVANTAGE_ECN_MT4 = 52;
    public const FOLLOWER_FXTM_ADVANTAGE_ECN_MT4 = 53;
    public const FOLLOWER_AINT_TO_FXTM_ADVANTAGE_ECN_MT4 = 10027;


    //AINT ECN MT4
    //Aggregate and follower account types are MT5
    public const LEADER_AINT_ECN_MT4 = 10005;
    public const AGGREGATE_AINT_ECN_MT4 = 10022;
    public const FOLLOWER_AINT_ECN_MT4 = 10019;
    public const FOLLOWER_FXTM_TO_AINT_ECN_MT4 = 46;


    //AINT ECN MT5
    //Aggregate and follower account types are MT5
    public const LEADER_AINT_ECN_MT5 = 10007;
    public const AGGREGATE_AINT_ECN_MT5 = 10026;
    public const FOLLOWER_AINT_ECN_MT5 = 10025;
    public const FOLLOWER_FXTM_TO_AINT_ECN_MT5 = 51;


    //ABY ECN MT4
    //Aggregate and follower account types are MT5
    public const LEADER_ABY_ECN_MT4 = 10030;
    public const AGGREGATE_ABY_ECN_MT4 = 10034;
    public const FOLLOWER_ABY_ECN_MT4 = 10032;


    //ABY ECN ZERO MT4
    //Aggregate and follower account types are MT5
    public const LEADER_ABY_ECN_ZERO_MT4 = 10031;
    public const AGGREGATE_ABY_ECN_ZERO_MT4 = 10035;
    public const FOLLOWER_ABY_ECN_ZERO_MT4 = 10033;

    private static $aggregateTypesByLeaderTypes = [
        self::LEADER_FXTM_ECN_MT4 => self::AGGREGATE_FXTM_ECN_MT4,
        self::LEADER_FXTM_ECN_ZERO_MT4 => self::AGGREGATE_FXTM_ECN_ZERO_MT4,
        self::LEADER_FXTM_ADVANTAGE_ECN_MT4 => self::AGGREGATE_FXTM_ADVANTAGE_ECN_MT4,
        self::LEADER_AINT_ECN_MT4 => self::AGGREGATE_AINT_ECN_MT4,
        self::LEADER_AINT_ECN_MT5 => self::AGGREGATE_AINT_ECN_MT5,
        self::LEADER_ABY_ECN_MT4 => self::AGGREGATE_ABY_ECN_MT4,
        self::LEADER_ABY_ECN_ZERO_MT4 => self::AGGREGATE_ABY_ECN_ZERO_MT4,
    ];

    private static $followerTypesByLeaderTypes = [
        Broker::ALPARI => [
            self::LEADER_FXTM_ECN_MT4 => self::FOLLOWER_AINT_TO_FXTM_ECN_MT4,
            self::LEADER_FXTM_ECN_ZERO_MT4 => self::FOLLOWER_AINT_TO_FXTM_ECN_ZERO_MT4,
            self::LEADER_FXTM_ADVANTAGE_ECN_MT4 => self::FOLLOWER_AINT_TO_FXTM_ADVANTAGE_ECN_MT4,
            self::LEADER_AINT_ECN_MT4 => self::FOLLOWER_AINT_ECN_MT4,
            self::LEADER_AINT_ECN_MT5 => self::FOLLOWER_AINT_ECN_MT5,
        ],
        Broker::FXTM => [
            self::LEADER_FXTM_ECN_MT4 => self::FOLLOWER_FXTM_ECN_MT4,
            self::LEADER_FXTM_ECN_ZERO_MT4 => self::FOLLOWER_FXTM_ECN_ZERO_MT4,
            self::LEADER_FXTM_ADVANTAGE_ECN_MT4 => self::FOLLOWER_FXTM_ADVANTAGE_ECN_MT4,
            self::LEADER_AINT_ECN_MT4 => self::FOLLOWER_FXTM_TO_AINT_ECN_MT4,
            self::LEADER_AINT_ECN_MT5 => self::FOLLOWER_FXTM_TO_AINT_ECN_MT5,
        ],
        Broker::ABY => [
            self::LEADER_ABY_ECN_MT4 => self::FOLLOWER_ABY_ECN_MT4,
            self::LEADER_ABY_ECN_ZERO_MT4 => self::FOLLOWER_ABY_ECN_ZERO_MT4,
        ],
    ];

    public static function GetAggregateTypeByLeaderType(int $leaderType): int
    {
        if (empty($type = self::$aggregateTypesByLeaderTypes[$leaderType])) {
            throw new \RuntimeException("Undefined leader account type '{$leaderType}'");
        }

        return $type;
    }

    public static function GetFollowerTypeByLeaderType($leaderType, string $broker): int
    {
        if (empty(self::$followerTypesByLeaderTypes[$broker])) {
            throw new \RuntimeException("Undefined broker: '$broker'");
        }
        if (empty(self::$followerTypesByLeaderTypes[$broker][$leaderType])) {
            throw new \RuntimeException("Undefined leader account type '{$leaderType}'");
        }

        return self::$followerTypesByLeaderTypes[$broker][$leaderType];
    }
}
