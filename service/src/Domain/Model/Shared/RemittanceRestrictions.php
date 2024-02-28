<?php


namespace Fxtm\CopyTrading\Domain\Model\Shared;


class RemittanceRestrictions
{
    const TRANSFER_TYPE_DEPOSIT  = 1;
    const TRANSFER_TYPE_WITHDRAW = 2;
    const TRANSFER_TYPE_TRANSFER = 3;

    const DIRECTION_FROM = -1;
    const DIRECTION_TO = 1;

    const RESTRICTION_OPEN_POSITIONS_CODE = 20036;
    const RESTRICTION_OPEN_POSITIONS_MSG = 'Account has open positions or active investors';

    const RESTRICTION_MINIMUM_EQUITY_CODE = 20037;
    const RESTRICTION_MINIMUM_EQUITY_MSG = 'Manager must keep minimal equity on his account.';

    const RESTRICTION_MAX_EQUITY_CODE = 20038;
    const RESTRICTION_MAX_EQUITY_MSG = 'Maximum allowed equity exceeded.';

}