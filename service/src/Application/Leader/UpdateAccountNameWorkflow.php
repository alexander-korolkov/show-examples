<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\Common\BaseWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\Activity;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class UpdateAccountNameWorkflow extends BaseWorkflow
{
    const TYPE = "leader.update_account_name";

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    /**
     * UpdateAccountNameWorkflow constructor.
     * @param LeaderAccountRepository $leadAccRepo
     */
    public function __construct(
        LeaderAccountRepository $leadAccRepo
    ) {
        $this->leadAccRepo = $leadAccRepo;

        parent::__construct(
            $this->activities([
                'updateAccountName',
            ])
        );
    }

    protected function updateAccountName(Activity $activity): void
    {
        $leadAcc = $this->leadAccRepo->getLightAccountOrFail($this->getAccountNumber());
        $leadAcc->updateName($this->getContext()->get("accName"));
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
