<?php

namespace Fxtm\CopyTrading\Domain\Model\Follower;

use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

interface CommissionRepository
{
    public function store(Commission $comm);

    /**
     * @param $id
     * @return Commission
     */
    public function findByWorkflowId($id);

    /**
     * @param $id
     * @return Commission
     */
    public function findByTransId($id);
    public function findByAccountNumberForPeriod(AccountNumber $accNo, $start, $end);

    /**
     * Returns true if given account has only one END_OF_INTERVAL type commission
     *
     * @param AccountNumber $accNo
     * @return bool
     */
    public function isFirstPayout(AccountNumber $accNo) : bool;

    /**
     * Returns latest fee of type PERIODICAL_PAYOUT or ACCOUNT_CLOSING
     *
     * @param AccountNumber $accNo
     * @return array
     */
    public function getLatestForStatement(AccountNumber $accNo);

    /**
     * Returns latest fee of type PERIODICAL_PAYOUT if it exists
     *
     * @param AccountNumber $accNo
     * @return array
     */
    public function getLastPayout(AccountNumber $accNo);

    /**
     * Returns previous before latest fee of type PERIODICAL_PAYOUT if it exists
     *
     * @param AccountNumber $accNo
     * @return array
     */
    public function getPreviousPayout(AccountNumber $accNo);
}
