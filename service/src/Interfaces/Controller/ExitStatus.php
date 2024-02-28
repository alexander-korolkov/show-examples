<?php

namespace Fxtm\CopyTrading\Interfaces\Controller;

final class ExitStatus
{
    const SUCCESS = 0;

    const UNKNOWN_ERROR                          = 1;
    const LEADER_ACCOUNT_NAME_ALREADY_TAKEN      = 2;
    const FOLLOWER_WITHDRAWAL_NOT_ENOUGH_FUNDS   = 3;
    const LEADER_ACCOUNT_HAS_OPEN_POSITIONS      = 4;
    const ACCOUNT_ALREADY_CLOSED                 = 5;
    const INSUFFICIENT_FUNDS                     = 6;
    const ACCOUNT_TEMPORARILY_BLOCKED            = 7;
    const LEADER_ACCOUNT_BLOCKED                 = 8;
    const LEADER_ACCOUNT_CLOSED                  = 9;
    const LEADER_ACCOUNT_NOT_FOLLOWABLE          = 10;
    const LEADER_ACCOUNT_INVALID_LEVERAGE        = 11;
    const INCOMPATIBLE_APPROPRIATENESS_LEVERAGE  = 12;
    const INCOMPATIBLE_MAX_ALLOWED_LEVERAGE      = 13;
    const INCOMPATIBLE_COPY_COEFFICIENT          = 14;
    const FOLLOWER_ACC_COPYING_LOCKED            = 15;
    const FOLLOWER_ACC_COPY_COEFFICIENT_LOCKED   = 16;
    const LEADER_ACCOUNT_NAME_ALREADY_UPDATED    = 17;
    const LEADER_NICKNAME_ALREADY_TAKEN          = 18;
    const ACCOUNT_NOT_REGISTERED                 = 19;
    const NEWS_NOT_FOUND                         = 20;
    const NEWS_ALREADY_APPROVED                  = 21;
    const NEWS_ALREADY_SUBMITTED                 = 22;
    const LEADER_ACCOUNT_NAME_DECLINED_BY_CENSOR = 23;
    const LEADER_NICKNAME_DECLINED_BY_CENSOR     = 24;
    const ACCOUNT_DESCRIPTION_DECLINED_BY_CENSOR = 25;
    const NEWS_TEXT_DECLINED_BY_CENSOR           = 26;
    const QUESTIONNAIRE_NOT_FOUND                = 27;
    const EU_CLIENT_FORBIDDEN_TO_FOLLOW          = 28;
    const SELF_FOLLOWING_LIMIT                   = 29;
    const COUNTRY_FORBIDDEN_TO_FOLLOW            = 30;
    const ACCOUNT_OPENING_IS_BLOCKED             = 31;
    const LEADER_HAS_ACTIVE_INVESTORS            = 32;
    const FOLLOWER_ACC_NOT_ENOUGH_MONEY_TO_RESUME = 33;

    public static function toString($exitCode)
    {
        $map = [
            self::SUCCESS                               => "SUCCESS",
            self::UNKNOWN_ERROR                         => "UNKNOWN_ERROR",
            self::LEADER_ACCOUNT_NAME_ALREADY_TAKEN     => "LEADER_ACCOUNT_NAME_ALREADY_TAKEN",
            self::FOLLOWER_WITHDRAWAL_NOT_ENOUGH_FUNDS  => "FOLLOWER_WITHDRAWAL_NOT_ENOUGH_FUNDS",
            self::LEADER_ACCOUNT_HAS_OPEN_POSITIONS     => "LEADER_ACCOUNT_HAS_OPEN_POSITIONS",
            self::ACCOUNT_ALREADY_CLOSED                => "ACCOUNT_ALREADY_CLOSED",
            self::INSUFFICIENT_FUNDS                    => "INSUFFICIENT_FUNDS",
            self::ACCOUNT_TEMPORARILY_BLOCKED           => "ACCOUNT_TEMPORARILY_BLOCKED",
            self::LEADER_ACCOUNT_BLOCKED                => "LEADER_ACCOUNT_BLOCKED",
            self::LEADER_ACCOUNT_CLOSED                 => "LEADER_ACCOUNT_CLOSED",
            self::LEADER_ACCOUNT_NOT_FOLLOWABLE         => "LEADER_ACCOUNT_NOT_FOLLOWABLE",
            self::LEADER_ACCOUNT_INVALID_LEVERAGE       => "LEADER_ACCOUNT_INVALID_LEVERAGE",
            self::INCOMPATIBLE_APPROPRIATENESS_LEVERAGE => "INCOMPATIBLE_APPROPRIATENESS_LEVERAGE",
            self::INCOMPATIBLE_MAX_ALLOWED_LEVERAGE     => "INCOMPATIBLE_MAX_ALLOWED_LEVERAGE",
            self::INCOMPATIBLE_COPY_COEFFICIENT         => "INCOMPATIBLE_COPY_COEFFICIENT",
            self::FOLLOWER_ACC_COPYING_LOCKED           => "FOLLOWER_ACC_COPYING_LOCKED",
            self::FOLLOWER_ACC_COPY_COEFFICIENT_LOCKED  => "FOLLOWER_ACC_COPY_COEFFICIENT_LOCKED",
            self::LEADER_ACCOUNT_NAME_ALREADY_UPDATED   => "LEADER_ACCOUNT_NAME_ALREADY_UPDATED",
            self::LEADER_NICKNAME_ALREADY_TAKEN         => "LEADER_ACCOUNT_NAME_ALREADY_TAKEN",
            self::ACCOUNT_NOT_REGISTERED                => "ACCOUNT_NOT_REGISTERED",
            self::NEWS_NOT_FOUND                        => "NEWS_NOT_FOUND",
            self::NEWS_ALREADY_APPROVED                 => "NEWS_ALREADY_APPROVED",
            self::NEWS_ALREADY_SUBMITTED                => "NEWS_ALREADY_SUBMITTED",
            self::LEADER_ACCOUNT_NAME_DECLINED_BY_CENSOR => "LEADER_ACCOUNT_NAME_DECLINED_BY_CENSOR",
            self::LEADER_NICKNAME_DECLINED_BY_CENSOR    => "LEADER_NICKNAME_DECLINED_BY_CENSOR",
            self::ACCOUNT_DESCRIPTION_DECLINED_BY_CENSOR => "ACCOUNT_DESCRIPTION_DECLINED_BY_CENSOR",
            self::NEWS_TEXT_DECLINED_BY_CENSOR          => "NEWS_TEXT_DECLINED_BY_CENSOR",
            self::QUESTIONNAIRE_NOT_FOUND               => "QUESTIONNAIRE_NOT_FOUND",
            self::SELF_FOLLOWING_LIMIT                  => "SELF_FOLLOWING_LIMIT",
            self::COUNTRY_FORBIDDEN_TO_FOLLOW           => "COUNTRY_FORBIDDEN_TO_FOLLOW",
            self::ACCOUNT_OPENING_IS_BLOCKED            => "ACCOUNT_OPENING_IS_BLOCKED",
            self::LEADER_HAS_ACTIVE_INVESTORS           => "LEADER_HAS_ACTIVE_INVESTORS",
            self::FOLLOWER_ACC_NOT_ENOUGH_MONEY_TO_RESUME => "FOLLOWER_ACC_NOT_ENOUGH_MONEY_TO_RESUME",
        ];
        return strtolower($map[$exitCode]);
    }
}
