<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class ChangeRemunerationFeeWorkflow extends BaseWorkflow
{
    const TYPE = "leader.change_remun_fee";

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * ChangeRemunerationFeeWorkflow constructor.
     * @param LeaderAccountRepository $leadAccRepo
     */
    public function __construct(
        LeaderAccountRepository $leadAccRepo
    ) {
        $this->leadAccRepo = $leadAccRepo;

        parent::__construct(
            $this->activities([
                'changeRemunerationFee'
            ])
        );
    }

    protected function changeRemunerationFee(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $leadAcc->changeRemunerationFee($this->getRemunerationFee(), $this);
        try {
            $this->leadAccRepo->store($leadAcc);
        } catch (\Exception $e) {
            $this->logException($e);
            $activity->fail();
            return;
        }
        $activity->succeed();
    }

    private function getAccountNumber()
    {
        return new AccountNumber($this->getContext()->get("accNo"));
    }

    private function getRemunerationFee()
    {
        return $this->getContext()->get("remunFee");
    }

    public function getAccountRepository()
    {
        return $this->leadAccRepo;
    }

    /**
     * Method should return true if all necessary conditions for
     * creating of this workflow are met
     *
     * @return bool
     */
    public function canBeCreated()
    {
        return !empty($this->leadAccRepo->getLightAccount($this->getAccountNumber()));
    }
}
