<?php

namespace Fxtm\CopyTrading\Application;

use Fxtm\CopyTrading\Domain\Model\Client\ClientId;

interface NotificationGateway
{
    const LEADER_ACC_OPENED = "leader_acc_opened";
    const LEADER_ACC_REGISTERED = "leader_acc_registered";
    const LEADER_DEPOSIT_CLOSE_POSITIONS = "leader_deposit_close_positions";
    const LEADER_DEPOSIT_HAS_BEEN_TRANSFERRED_TO_WALLET = "leader_deposit_has_been_transferred_to_wallet";
    const LEADER_FUNDS_DEPOSITED = "leader_funds_deposited";
    const LEADER_FUNDS_DEPOSITED_INSUFFICIENT = "leader_funds_deposited_insufficient";
    const LEADER_WITHDRAWAL_CLOSE_POSITIONS = "leader_withdrawal_close_positions";
    const LEADER_FUNDS_WITHDRAWN = "leader_funds_withdrawn";
    const LEADER_FUNDS_WITHDRAWN_NO_ACTIVITY_FEE = "leader_funds_withdrawn_no_activity_fee";
    const LEADER_ACC_FIRST_FOLLOWER = "leader_acc_first_follower";
    const LEADER_ACC_DEACTIVATED = "leader_acc_deactivated";
    const LEADER_ACC_CLOSED = "leader_acc_closed";
    const LEADER_ACC_INACTIVE_NOTICE = "leader_acc_inactive_notice";
    const LEADER_ACC_HIDDEN = "leader_acc_hidden";
    const LEADER_ACC_SWITCHED_OFF = "leader_acc_switched_off";
    const LEADER_ACC_DESCRIPTION_APPROVED = "leader_acc_description_approved";
    const LEADER_ACC_DESCRIPTION_REJECTED = "leader_acc_description_rejected";
    const LEADER_ACC_INACTIVE_WARNING = "leader_acc_inactive_warning";
    const LEADER_ACC_INACTIVE_CLOSED = "leader_acc_inactive_closed";
    const FOLLOWER_ACC_OPENED = "follower_acc_opened";
    const FOLLOWER_FUNDS_DEPOSITED = "follower_funds_deposited";
    const FOLLOWER_FUNDS_DEPOSITED_INSUFFICIENT = "follower_funds_deposited_insufficient";
    const FOLLOWER_FUNDS_WITHDRAWN = "follower_funds_withdrawn";
    const FOLLOWER_ACC_CLOSED = "follower_acc_closed";
    const FOLLOWER_ACC_CLOSED_BY_LEADER = "follower_acc_closed_by_leader";
    const FOLLOWER_COPYING_STOPPED = "follower_copying_stopped";
    const FOLLOWER_STOPLOSS_REACHED = "follower_stoploss_reached";
    const FOLLOWER_STOPLOSS_REACHED_STOPPED = "follower_stoploss_reached_stopped";
    const FOLLOWER_STOPLOSS_ADJUSTED = "follower_stoploss_adjusted";
    const FOLLOWER_STATEMENT = "follower_statement";
    const FOLLOWER_CANNOT_RESUME_INSUFFICIENT_FUNDS = "follower_cannot_resume_insufficient_funds";
    const FOLLOWER_CANNOT_RESUME = "follower_cannot_resume";
    const FOLLOWER_ACC_CLOSED_INCOMPATIBLE_LEVERAGE = "follower_acc_closed_incompatible_leverage";
    const FOLLOWER_LOCKED_IN_SAFE_MODE = "follower_locked_in_safe_mode";
    const FOLLOWER_PAUSED_NO_APPRTEST = "follower_paused_no_apprtest";
    const FOLLOWER_ACC_LOSING = "follower_acc_losing";
    const FOLLOWER_ACC_INACTIVE_WARNING = "follower_acc_inactive_warning";
    const FOLLOWER_ACC_INACTIVE_CLOSED = "follower_acc_inactive_closed";
    const FOLLOWER_ACC_DISCONNECTED_FROM_INACTIVE_LEADER = "follower_acc_disconnected_from_inactive_leader";
    const QUESTIONNAIRE_SUCCESS = "questionnaire_success";
    const QUESTIONNAIRE_FAIL = "questionnaire_fail";
    const NEWS_APPROVED = "news_approved";
    const NEWS_REJECTED = "news_rejected";
    public const COPY_TRADING_LEADER_LOST_ALL_MONEY = 'copy_trading_leader_lost_all_money';
    public const COPY_TRADING_INVESTOR_LOST_ALL_MONEY = 'copy_trading_investor_lost_all_money';
    public const COPY_TRADING_INVESTOR_YOUR_LEADER_LOST_ALL_MONEY = 'copy_trading_investor_your_leader_lost_all_money';

    /**
     * @param ClientId $clientId
     * @param string $broker
     * @param $msgType
     * @param array $data
     * @return mixed
     */
    public function notifyClient(ClientId $clientId, $broker, $msgType, array $data = []);
}
