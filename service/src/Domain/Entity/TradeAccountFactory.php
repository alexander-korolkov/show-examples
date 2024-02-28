<?php

namespace Fxtm\CopyTrading\Domain\Entity;

class TradeAccountFactory
{
    public const ACCOUNT_TYPE_ECN_MT4 = 3;
    public const ACCOUNT_TYPE_ECN_ZERO = 23;
    public const AI_ECN_MT4_LIVE = 10005;
    public const AI_ECN_MT5_LIVE = 10007;
    public const ACCOUNT_TYPE_ADVANTAGE_ECN_MT4_LIVE = 50;
    public const ABY_MT4_ECN = 10030;
    public const ABY_MT4_ECN_ZERO = 10031;

    public static $ACCOUNT_TYPE_COPY_TRADING_LEADERS = [
        self::ACCOUNT_TYPE_ECN_MT4,
        self::ACCOUNT_TYPE_ECN_ZERO,
        self::AI_ECN_MT4_LIVE,
        self::AI_ECN_MT5_LIVE,
        self::ACCOUNT_TYPE_ADVANTAGE_ECN_MT4_LIVE,
        self::ABY_MT4_ECN,
        self::ABY_MT4_ECN_ZERO,
    ];

    public const ACCOUNT_TYPE_ECN_MT5_FOLLOWER = 44;
    public const ACCOUNT_TYPE_ABY_ECN_MT5_FOLLOWER = 10032;
    public const ACCOUNT_TYPE_ABY_ECN_ZERO_MT5_FOLLOWER = 10033;
    public const ACCOUNT_TYPE_ADVANTAGE_ECN_FOLLOWER = 53;
    public const ACCOUNT_TYPE_ECN_ZERO_MT5_FOLLOWER = 45;
    public const ACCOUNT_TYPE_TO_AI_ECN_FOLLOWER = 46;
    public const ACCOUNT_TYPE_TO_AI_ECN_MT5_FOLLOWER = 51;
    public const AI_ECN_MT5_FOLLOWER = 10019;
    public const AI_TO_FX_ECN_MT5_FOLLOWER = 10020;
    public const AI_TO_FX_ADVANTAGE_ECN_FOLLOWER = 10027;
    public const AI_TO_FX_ECN_ZERO_MT5_FOLLOWER = 10021;
    public const AI_MT5_ECN_MT5_FOLLOWER = 10025;
    public const FT_TO_AI_MT5_ECN_FOLLOWER = 1027;

    public static $ACCOUNT_TYPE_COPY_TRADING_FOLLOWERS = [
        self::ACCOUNT_TYPE_ECN_MT5_FOLLOWER,
        self::ACCOUNT_TYPE_ABY_ECN_MT5_FOLLOWER,
        self::ACCOUNT_TYPE_ABY_ECN_ZERO_MT5_FOLLOWER,
        self::ACCOUNT_TYPE_ADVANTAGE_ECN_FOLLOWER,
        self::ACCOUNT_TYPE_ECN_ZERO_MT5_FOLLOWER,
        self::ACCOUNT_TYPE_TO_AI_ECN_FOLLOWER,
        self::ACCOUNT_TYPE_TO_AI_ECN_MT5_FOLLOWER,
        self::AI_ECN_MT5_FOLLOWER,
        self::AI_TO_FX_ECN_MT5_FOLLOWER,
        self::AI_TO_FX_ADVANTAGE_ECN_FOLLOWER,
        self::AI_TO_FX_ECN_ZERO_MT5_FOLLOWER,
        self::AI_MT5_ECN_MT5_FOLLOWER,
        self::FT_TO_AI_MT5_ECN_FOLLOWER,
    ];

    public static function isFollowerAccountType($accTypeId)
    {
        return in_array(
            $accTypeId,
            self::$ACCOUNT_TYPE_COPY_TRADING_FOLLOWERS
        );
    }

    public static function isLeaderAccountType($accTypeId)
    {
        return in_array(
            $accTypeId,
            self::$ACCOUNT_TYPE_COPY_TRADING_LEADERS
        );
    }

}
