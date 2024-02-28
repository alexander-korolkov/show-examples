<?php

namespace Fxtm\CopyTrading\Application\Services\FollowerAccount;

use Exception;
use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\Common\Environment;
use Fxtm\CopyTrading\Domain\Model\Follower\Commission;
use Fxtm\CopyTrading\Server\Generated\Model\InvestorAccountMinDeposit;
use Fxtm\CopyTrading\Server\Generated\Model\InvestorPaidFee;
use Fxtm\CopyTrading\Server\Generated\Model\InvestorPaidFees;
use Psr\Log\LoggerInterface as Logger;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Follower\ChangeCopyCoefficientWorkflow;
use Fxtm\CopyTrading\Application\Follower\ChangeStopLossSettingsWorkflow;
use Fxtm\CopyTrading\Application\Follower\CloseAccountWorkflow;
use Fxtm\CopyTrading\Application\Follower\IncompatibleAppropriatenessLeverage;
use Fxtm\CopyTrading\Application\Follower\IncompatibleMaxAllowedLeverage;
use Fxtm\CopyTrading\Application\Follower\OpenAccountWorkflow;
use Fxtm\CopyTrading\Application\Follower\PauseCopyingWorkflow;
use Fxtm\CopyTrading\Application\Follower\ProcessDepositWorkflow;
use Fxtm\CopyTrading\Application\Follower\ProcessPayoutWorkflow;
use Fxtm\CopyTrading\Application\Follower\ProcessWithdrawalWorkflow;
use Fxtm\CopyTrading\Application\Follower\ResumeCopyingWorkflow;
use Fxtm\CopyTrading\Application\FollowerTradeHistory;
use Fxtm\CopyTrading\Application\InvestorProfit\InvestorProfitService;
use Fxtm\CopyTrading\Application\Leader\DeleteAccountWorkflow;
use Fxtm\CopyTrading\Application\LeverageService;
use Fxtm\CopyTrading\Application\Metrix\MetrixData;
use Fxtm\CopyTrading\Application\Security\Role;
use Fxtm\CopyTrading\Application\Services\LoggerTrait;
use Fxtm\CopyTrading\Application\Services\SecurityTrait;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Company\Company;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccount;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\QuestionnaireAttempt\QuestionnaireAttemptRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;
use Fxtm\CopyTrading\Domain\Model\Shared\RemittanceRestrictions;
use Fxtm\CopyTrading\Interfaces\Controller\ExitStatus;
use Fxtm\CopyTrading\Interfaces\Repository\BrokerRepository;
use Fxtm\CopyTrading\Server\Generated\Api\InvestorAccountApiInterface;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeCopyCoefficientRequest;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeProtectionLevelRequest;
use Fxtm\CopyTrading\Server\Generated\Model\ClosedInvestorAccount;
use Fxtm\CopyTrading\Server\Generated\Model\ClosedOrder;
use Fxtm\CopyTrading\Server\Generated\Model\CreateFollowerRequest;
use Fxtm\CopyTrading\Server\Generated\Model\CreateAccountResponse;
use Fxtm\CopyTrading\Server\Generated\Model\CreateWorkflowResult;
use Fxtm\CopyTrading\Server\Generated\Model\FollowerTradingStatistics;
use Fxtm\CopyTrading\Server\Generated\Model\FollowerTradingStatsResponse;
use Fxtm\CopyTrading\Server\Generated\Model\InvestorAccount;
use Fxtm\CopyTrading\Server\Generated\Model\InvestorAccountStats;
use Fxtm\CopyTrading\Server\Generated\Model\InvestorAccountStatsAllTime;
use Fxtm\CopyTrading\Server\Generated\Model\InvestorPendingWorkflows;
use Fxtm\CopyTrading\Server\Generated\Model\InvestorPendingWorkflowsResult;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderReferrable;
use Fxtm\CopyTrading\Server\Generated\Model\LightInvestorAccount;
use Fxtm\CopyTrading\Server\Generated\Model\LightStrategyManager;
use Fxtm\CopyTrading\Server\Generated\Model\RemittanceRestrictionsCheckRequest;
use Fxtm\CopyTrading\Server\Generated\Model\RemittanceRestrictionsCheckStatus;
use Fxtm\CopyTrading\Server\Generated\Model\OpenOrder;
use Fxtm\CopyTrading\Server\Generated\Model\WithdrawalRequest;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class FollowerAccountService implements InvestorAccountApiInterface
{
    use LoggerTrait;
    use SecurityTrait;

    private const FEE_TYPE_TO_REASON_RELATION = [
        Commission::TYPE_PERIODICAL => 'monthly_fee',
        Commission::TYPE_WITHDRAWAL => 'partial_withdrawal',
        Commission::TYPE_CLOSE_ACCOUNT => 'account_closing',
    ];
    private $restrictedCountriesList = ['RU', 'IR'];

    /**
     * @var FollowerAccountRepository
     */
    private $followerAccountRepository;

    /**
     * @var InvestorProfitService
     */
    private $profitService;

    /**
     * @var BrokerRepository
     */
    private $brokerRepository;

    /**
     * @var WorkflowManager
     */
    private $workflowManager;

    /**
     * @var WorkflowRepository
     */
    private $workflowRepository;

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var LeverageService
     */
    private $leverageService;

    /**
     * @var SettingsRegistry
     */
    private $settingsRegistry;

    /**
     * @var ClientGateway
     */
    private $clientGateway;

    /**
     * @var QuestionnaireAttemptRepository
     */
    private $questionnaireAttemptRepository;

    /**
     * @var FollowerTradeHistory
     */
    private $followerTradeHistory;

    /**
     * FollowerAccountService constructor.
     * @param FollowerAccountRepository $followerAccountRepository
     * @param InvestorProfitService $profitService
     * @param BrokerRepository $brokerRepository
     * @param WorkflowManager $workflowManager
     * @param WorkflowRepository $workflowRepository
     * @param LeaderAccountRepository $leaderAccountRepository
     * @param LeverageService $leverageService
     * @param SettingsRegistry $settingsRegistry
     * @param ClientGateway $clientGateway
     * @param QuestionnaireAttemptRepository $questionnaireAttemptRepository
     * @param FollowerTradeHistory $tradeHistory
     * @param Security $security
     * @param Logger $logger
     */
    public function __construct(
        FollowerAccountRepository $followerAccountRepository,
        InvestorProfitService $profitService,
        BrokerRepository $brokerRepository,
        WorkflowManager $workflowManager,
        WorkflowRepository $workflowRepository,
        LeaderAccountRepository $leaderAccountRepository,
        LeverageService $leverageService,
        SettingsRegistry $settingsRegistry,
        ClientGateway $clientGateway,
        QuestionnaireAttemptRepository $questionnaireAttemptRepository,
        FollowerTradeHistory $tradeHistory,
        Security $security,
        Logger $logger
    ) {
        $this->followerAccountRepository = $followerAccountRepository;
        $this->profitService = $profitService;
        $this->brokerRepository = $brokerRepository;
        $this->workflowManager = $workflowManager;
        $this->workflowRepository = $workflowRepository;
        $this->leaderAccountRepository = $leaderAccountRepository;
        $this->leverageService = $leverageService;
        $this->settingsRegistry = $settingsRegistry;
        $this->clientGateway = $clientGateway;
        $this->questionnaireAttemptRepository = $questionnaireAttemptRepository;
        $this->followerTradeHistory = $tradeHistory;
        $this->setSecurityHandler($security);
        $this->setLogger($logger);
    }


    /**
     * Sets authentication method jwt
     *
     * @param string $value Value of the jwt authentication method.
     *
     * @return void
     */
    public function setjwt($value)
    {
    }

    /**
     * Operation clientClientIdClosedInvestorAccountsGet
     *
     * Get closed investor accounts list for concrete client
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  float $limit (optional)
     * @param  float $offset (optional)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ClosedInvestorAccount[]
     *
     * @throws Exception
     */
    public function clientClientIdClosedInvestorAccountsGet($clientId, $limit = null, $offset = null, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $accounts = $this
                ->followerAccountRepository
                ->getClosedByClient(
                    intval($clientId),
                    empty($limit) ? 1 : intval($limit),
                    empty($offset) ? 1 : intval($offset)
                );

            return array_map(function (array $account) {
                return new ClosedInvestorAccount($account);
            }, $accounts);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation clientClientIdInvestorAccountsGet
     *
     * Returns investor accounts for concrete client
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  string[] $logins  show only accounts with given logins (optional)
     * @param  int $limit   (optional)
     * @param  int $offset   (optional)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return InvestorAccount[]
     *
     * @throws Exception
     */
    public function clientClientIdInvestorAccountsGet($clientId, array $logins = null, $limit = null, $offset = null, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $clientId = intval($clientId);

            $results = $this
                ->followerAccountRepository
                ->getByClient(
                    $clientId,
                    empty($logins) ? null : $logins,
                    empty($limit) ? null : intval($limit),
                    empty($offset) ? null : intval($offset)
                );

            $broker = $this
                ->brokerRepository
                ->getByFollowerClientId($clientId);

            return array_map(function (array $result) use ($broker) {
                $result['leader'] = new LightStrategyManager($result);
                $result['leader']->setAccountNumber($result['leaderAccountNumber']);
                $accountNumber = $result['accountNumber'];

                $equity = $this->profitService->getLatestBalanceAndEquity($accountNumber, $broker)['equity'];

                $account = new InvestorAccount($result);
                $account->setEquity($equity);
                $account->setProfitTd($this->profitService->calculateTodayProfit($accountNumber, $equity));
                $account->setProfitYd($this->profitService->calculateYesterdayProfit($accountNumber, $equity));
                $account->setProfit1w($this->profitService->calculateWeekProfit($accountNumber, $equity));
                $account->setProfit1m($this->profitService->calculateMonthProfit($accountNumber, $equity));
                $account->setProfit3m($this->profitService->calculateThreeMonthProfit($accountNumber, $equity));
                $account->setProfit6m($this->profitService->calculateSixMonthProfit($accountNumber, $equity));
                $account->setProfitAll($this->profitService->calculateTotalProfit($accountNumber, $equity));

                return $account;
            }, $results);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersAccountNumberClosePost
     *
     * Close follower account
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateAccountResponse
     *
     * @throws Exception
     */
    public function followersAccountNumberClosePost($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            if ($this->workflowRepository->isPending(CloseAccountWorkflow::TYPE, $accountNumber)) {
                return new CreateAccountResponse([
                    'status' => ExitStatus::ACCOUNT_ALREADY_CLOSED,
                ]);
            }

            $broker = $this->brokerRepository->getByFollower($accountNumber);
            $workflow = $this->workflowManager->newWorkflow(
                CloseAccountWorkflow::TYPE,
                new ContextData([
                    'accNo' => $accountNumber,
                    ContextData::KEY_BROKER => $broker,
                ])
            );
            $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateAccountResponse([
                'status' => ExitStatus::SUCCESS,
                'result' => $accountNumber,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersAccountNumberCopyCoefficientPatch
     *
     * Change follower's copy coefficient
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  ChangeCopyCoefficientRequest $changeCopyCoefficientRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function followersAccountNumberCopyCoefficientPatch($accountNumber, ChangeCopyCoefficientRequest $changeCopyCoefficientRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $broker = $this->brokerRepository->getByFollower($accountNumber);
            $account = $this->followerAccountRepository->getLightAccountOrFail(new AccountNumber($accountNumber));
            if ($account->isCopyCoefficientLocked()) {
                return new CreateWorkflowResult([
                    'status' => ExitStatus::FOLLOWER_ACC_COPY_COEFFICIENT_LOCKED,
                ]);
            }

            $workflow = $this->workflowManager->newWorkflow(
                ChangeCopyCoefficientWorkflow::TYPE,
                new ContextData([
                    'accNo' => $accountNumber,
                    'copyCoef' => $changeCopyCoefficientRequest->getCopyCoefficient(),
                    ContextData::KEY_BROKER => $broker,
                ])
            );
            $result = $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $result,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersAccountNumberGet
     *
     * Returns investor account
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return InvestorAccount
     *
     * @throws Exception
     */
    public function followersAccountNumberGet($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $result = $this
                ->followerAccountRepository
                ->getAsArray(intval($accountNumber));

            if ($result == null) {
                $responseCode = 404;
                return null;
            }

            $result['leader'] = new LightStrategyManager($result);
            $result['leader']->setAccountNumber($result['leaderAccountNumber']);
            $accountNumber = $result['accountNumber'];
            $broker = $this->brokerRepository->getByFollower($accountNumber);

            $equity = $this->profitService->getLatestBalanceAndEquity($accountNumber, $broker)['equity'];

            $account = new InvestorAccount($result);
            $account->setEquity($equity);
            $account->setProfitTd($this->profitService->calculateTodayProfit($accountNumber, $equity));
            $account->setProfitYd($this->profitService->calculateYesterdayProfit($accountNumber, $equity));
            $account->setProfit1w($this->profitService->calculateWeekProfit($accountNumber, $equity));
            $account->setProfit1m($this->profitService->calculateMonthProfit($accountNumber, $equity));
            $account->setProfit3m($this->profitService->calculateThreeMonthProfit($accountNumber, $equity));
            $account->setProfit6m($this->profitService->calculateSixMonthProfit($accountNumber, $equity));
            $account->setProfitAll($this->profitService->calculateTotalProfit($accountNumber, $equity));

            return $account;
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersAccountNumberPausePost
     *
     * Pause copying
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function followersAccountNumberPausePost($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $account = $this->followerAccountRepository->getLightAccountOrFail(new AccountNumber($accountNumber));
            if ($account->isCopyingLocked()) {
                return new CreateWorkflowResult([
                    'status' => ExitStatus::FOLLOWER_ACC_COPYING_LOCKED,
                ]);
            }

            $workflow = $this->workflowManager->newWorkflow(
                PauseCopyingWorkflow::TYPE,
                new ContextData([
                    'accNo' => $accountNumber,
                    ContextData::KEY_BROKER => $account->broker(),
                ])
            );
            $result = $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $result,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersAccountNumberPendingWorkflowsGet
     *
     * Get pending workflows for investor account
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return InvestorPendingWorkflows
     *
     * @throws Exception
     */
    public function followersAccountNumberPendingWorkflowsGet($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $result = [
                'isBeingPaused' => false,
                'isBeingResumed' => false,
                'levelIsBeingUpdated' => false,
                'modeIsBeingUpdated' => false,
                'isBeingLiquidated' => false,
                'isBeingDeposited' => false,
                'isBeingWithdrew' => false,
                'isPeriodClosing' => false,
                'strategyIsBeingLiquidated' => false,
            ];

            $types = $this->workflowRepository->getPendingTypes($accountNumber);
            foreach ($types as $type) {
                if (!$result['isBeingPaused'] && !$result['isBeingResumed']) {
                    if (PauseCopyingWorkflow::TYPE == $type) {
                        $result['isBeingPaused'] = true;
                    } elseif (ResumeCopyingWorkflow::TYPE == $type) {
                        $result['isBeingResumed'] = true;
                    }
                }
                if (ChangeStopLossSettingsWorkflow::TYPE == $type) {
                    $result['levelIsBeingUpdated'] = true;
                }
                if (ChangeCopyCoefficientWorkflow::TYPE == $type) {
                    $result['modeIsBeingUpdated'] = true;
                }

                if (CloseAccountWorkflow::TYPE == $type) {
                    $result['isBeingLiquidated'] = true;
                }
                if (ProcessDepositWorkflow::TYPE == $type) {
                    $result['isBeingDeposited'] = true;
                }
                if (ProcessWithdrawalWorkflow::TYPE == $type) {
                    $result['isBeingWithdrew'] = true;
                }
                if (ProcessPayoutWorkflow::TYPE == $type) {
                    $result['isPeriodClosing'] = true;
                }

                $leadTypes = $this->workflowRepository->getPendingTypes(
                    $this->followerAccountRepository->getLightAccount(new AccountNumber($accountNumber))->leaderAccountNumber()->value()
                );
                foreach ($leadTypes as $leadType) {
                    if (DeleteAccountWorkflow::TYPE == $leadType) {
                        $result['strategyIsBeingLiquidated'] = true;
                    }
                }
            }

            return new InvestorPendingWorkflows([
                'result' => new InvestorPendingWorkflowsResult($result),
                'status' => ExitStatus::SUCCESS
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersAccountNumberProtectionLevelPatch
     *
     * Change follower's protection level
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  ChangeProtectionLevelRequest $changeProtectionLevelRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function followersAccountNumberProtectionLevelPatch($accountNumber, ChangeProtectionLevelRequest $changeProtectionLevelRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $workflow = $this->workflowManager->newWorkflow(
                ChangeStopLossSettingsWorkflow::TYPE,
                new ContextData([
                    'accNo' => $accountNumber,
                    'stopLossLevel' => $changeProtectionLevelRequest->getProtectionLevel(),
                    'stopCopyingOnStopLoss' => $changeProtectionLevelRequest->isStopCopying(),
                    ContextData::KEY_BROKER => $this->brokerRepository->getByFollower($accountNumber),
                ])
            );
            $result = $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $result,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersAccountNumberResumePost
     *
     * Resume copying
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function followersAccountNumberResumePost($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $broker = $this->brokerRepository->getByFollower($accountNumber);
            $account = $this->followerAccountRepository->findOrFail(new AccountNumber($accountNumber));
            if ($account->isCopyingLocked()) {
                return new CreateWorkflowResult([
                    'status' => ExitStatus::FOLLOWER_ACC_COPYING_LOCKED,
                ]);
            }

            $followerEquity = $account->equity()->amount();
            if(intval(floor($followerEquity * 100.0)) == 0) {
                return new CreateWorkflowResult([
                    'status' => ExitStatus::FOLLOWER_ACC_NOT_ENOUGH_MONEY_TO_RESUME,
                ]);
            }

            $leaderAccount = $this->leaderAccountRepository->findOrFail($account->leaderAccountNumber());
            $leaderEquity  = $leaderAccount->equity()->amount();
            $minEquityRatio = $account->isInSafeMode() ? 50.0 : 100.0;
            if(($leaderEquity / $followerEquity) > $minEquityRatio) {
                return new CreateWorkflowResult([
                    'status' => ExitStatus::FOLLOWER_ACC_NOT_ENOUGH_MONEY_TO_RESUME,
                ]);
            }

            $workflow = $this->workflowManager->newWorkflow(
                ResumeCopyingWorkflow::TYPE,
                new ContextData([
                    'accNo' => $accountNumber,
                    ContextData::KEY_BROKER => $broker,
                ])
            );
            $result = $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $result,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersAccountNumberStatsGet
     *
     * Returns investor account's statistics
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return InvestorAccountStats
     *
     * @throws Exception
     */
    public function followersAccountNumberStatsGet($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $followerAccount = $this->followerAccountRepository->getLightAccount(new AccountNumber(intval($accountNumber)));
            if ($followerAccount == null) {
                $responseCode = 404;
                return null;
            }

            $stats = $this->profitService->getDailyStats($accountNumber);
            foreach ($stats['allTime'] as $key => $value) {
                $stats['allTime'][$key] = new InvestorAccountStatsAllTime($value);
            }
            foreach ($stats['lastMonth'] as $key => $value) {
                $stats['lastMonth'][$key] = new InvestorAccountStatsAllTime($value);
            }

            return new InvestorAccountStats($stats);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersAccountNumberPaidFeesGet
     *
     * Returns investor account's paid fees
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  int $limit   (optional)
     * @param  int $offset   (optional)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return InvestorPaidFees
     *
     * @throws Exception
     */
    public function followersAccountNumberPaidFeesGet(
        $accountNumber,
        $limit = null,
        $offset = null,
        &$responseCode,
        array &$responseHeaders
    ) {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $accNo = new AccountNumber(intval($accountNumber));
            $fees = $this->followerAccountRepository->getPaidFees($accNo, $limit, $offset);
            $totalAmount = $this->followerAccountRepository->getPaidFeesTotalAmount($accNo);
            $totalItems = $this->followerAccountRepository->getPaidFeesCount($accNo);

            $items = [];
            foreach ($fees as $fee) {
                $items[] = new InvestorPaidFee([
                    'date' => $fee['created_at'],
                    'amount' => $fee['amount'],
                    'reason' => self::FEE_TYPE_TO_REASON_RELATION[$fee['type']],
                ]);
            }

            return new InvestorPaidFees([
                'totalAmountPaid' => $totalAmount,
                'totalItems' => $totalItems,
                'items' => $items,
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersAccountNumberTradingStatsDateFromDateToGet
     *
     * Returns followers trading statistics (summary)
     *
     * @param  string $accountNumber  trade account&#39;s login (required)
     * @param  string $dateFrom   (required)
     * @param  string $dateTo   (required)
     * @param  integer $responseCode     The HTTP response code to return
     * @param  array   $responseHeaders  Additional HTTP headers to return with the response ()
     *
     * @return FollowerTradingStatsResponse
     * @throws Exception
     */
    public function followersAccountNumberTradingStatsDateFromDateToGet($accountNumber, $dateFrom, $dateTo, &$responseCode, array &$responseHeaders)
    {
        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        }

        try {
            $response = new FollowerTradingStatsResponse();
            try {
                $dtFrom = DateTime::of($dateFrom);
                $dtFrom->setTime(0, 0, 0);

                $dtTo = DateTime::of($dateTo);
                $dtTo->setTime(23, 59, 59);

                $response->setClosedOrders(
                    $this
                        ->followerTradeHistory
                        ->getClosedOrdersCount(
                            new AccountNumber(intval($accountNumber)),
                            $dtFrom,
                            $dtTo
                        )
                );
            } catch (InvalidArgumentException $ignore) {
                $responseCode = 404;
                return null;
            }
            return $response;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * @param string $accountNumber
     * @param string $dateFrom
     * @param string $dateTo
     * @param int $responseCode
     * @param array $responseHeaders
     * @return ClosedOrder[]
     * @throws Exception
     */
    public function followersAccountNumberTradingHistoryDateFromDateToGet($accountNumber, $dateFrom, $dateTo, &$responseCode, array &$responseHeaders)
    {
        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        }

        try {
            $response = [];
            try {
                $dtFrom = DateTime::of($dateFrom);
                $dtFrom->setTime(0, 0, 0);

                $dtTo = DateTime::of($dateTo);
                $dtTo->setTime(23, 59, 59);

                $orders = $this
                    ->followerTradeHistory
                    ->getClosedOrders(
                        new AccountNumber(intval($accountNumber)),
                        $dtFrom,
                        $dtTo
                    );
                foreach ($orders as $order) {
                    $response[] = new ClosedOrder($order);
                }
            } catch (InvalidArgumentException $ignore) {
                $responseCode = 404;
                return null;
            }
            return $response;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    public function followersAccountNumberTradeStatisticsGet($accountNumber, &$responseCode, array &$responseHeaders)
    {
        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        }

        try {
            $followerAccount = $this
                ->followerAccountRepository
                ->getLightAccountOrFail(new AccountNumber($accountNumber))
            ;

            $leaderAccount = $this->leaderAccountRepository
                ->getLightAccountOrFail($followerAccount->leaderAccountNumber());

            if (!$leaderAccount->isShowTradingDetails()) {
                $responseCode = 403;
                return null;
            }

            $balanceAndEquity = $this
                ->profitService
                ->getLatestBalanceAndEquity($followerAccount->number()->value())
            ;

            $response = new FollowerTradingStatistics();
            $response->setEquity($balanceAndEquity['equity']);
            $response->setBalance($balanceAndEquity['balance']);
            $response->setCurrentResult(
                $balanceAndEquity['equity'] - $balanceAndEquity['balance']
            );

            try {
                $closedOrders = [];
                $orders = $this
                    ->followerTradeHistory
                    ->getLatestClosedOrders(
                        new AccountNumber(intval($accountNumber)),
                        50
                    );
                foreach ($orders as $order) {
                    $closedOrders[] = new ClosedOrder($order);
                }
                $response->setClosedOrders($closedOrders);
                $openOrders = [];
                $orders = $this
                    ->followerTradeHistory
                    ->getOpenOrders(
                        new AccountNumber(intval($accountNumber))
                    );

                $aggregated = [];
                foreach ($orders as $order) {
                    if (!isset($aggregated[$order['symbol']])) {
                        $aggregated[$order['symbol']] = [];
                    }
                    if (!isset($aggregated[$order['symbol']][$order['orderType']])) {
                        $aggregated[$order['symbol']][$order['orderType']] = 0.0;
                    }
                    $aggregated[$order['symbol']][$order['orderType']] += $order['volume'];
                }
                foreach ($aggregated as $symbol => $volumes) {
                    foreach ($volumes as $orderType => $volume) {
                        $openOrders[] = new OpenOrder([
                            'ticket'        => 0,
                            'orderType'     => $orderType,
                            'volume'        => $volume,
                            'symbol'        => $symbol,
                            'openTime'      => 0,
                            'openPrice'     => 0.0,
                            'stopLoss'      => 0.0,
                            'takeProfit'    => 0.0
                        ]);
                    }
                }
                $response->setOpenOrders($openOrders);
            } catch (InvalidArgumentException $ignore) {
                $responseCode = 404;
                return null;
            }
            return $response;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersAccountNumberWithdrawPost
     *
     * Withdraw funds
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  WithdrawalRequest $withdrawalRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateWorkflowResult
     *
     * @throws Exception
     */
    public function followersAccountNumberWithdrawPost($accountNumber, WithdrawalRequest $withdrawalRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $account = $this->followerAccountRepository->find(new AccountNumber($accountNumber));
            $funds = new Money($withdrawalRequest->getAmount(), $account->currency());
            if ($funds->amount() <= 0) {
                throw new InvalidArgumentException("Withdrawal amount <= 0");
            }
            if ($funds->isGreaterThan($account->equity())) {
                throw new InvalidArgumentException("Withdrawal amount > equity");
            }

            $workflow = $this->workflowManager->newWorkflow(
                ProcessWithdrawalWorkflow::TYPE,
                new ContextData([
                    "accNo" => $accountNumber,
                    "amount" => $funds->amount(),
                    "accCurr" => $account->currency()->code(),
                    "clientId" => $account->ownerId()->value(),
                    ContextData::KEY_BROKER => $account->broker(),
                ])
            );
            $result = $this->workflowManager->enqueueWorkflow($workflow);

            return new CreateWorkflowResult([
                'status' => ExitStatus::SUCCESS,
                'result' => $result,
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersClientIdReferrableGet
     *
     * Get referrable by owner
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return LeaderReferrable[]
     *
     * @throws Exception
     */
    public function followersClientIdReferrableGet($clientId, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN]);

            $data = $this
                ->followerAccountRepository
                ->getReferrable(intval($clientId));

            return array_map(function (array $referrable) {
                return new LeaderReferrable([
                    'follAccNo' => $referrable['foll_acc_no'],
                    'leadAccNo' => $referrable['lead_acc_no'],
                    'leadAccName' => $referrable['lead_acc_name'],
                    'leadAccProfit' => $referrable['lead_acc_profit'],
                ]);
            }, $data);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersPost
     *
     * Creates new investor account
     *
     * @param  CreateFollowerRequest $createFollowerRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return CreateAccountResponse
     *
     * @throws Exception
     */
    public function followersPost(CreateFollowerRequest $createFollowerRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $isAllowedAccountOpening = (bool) $this->settingsRegistry->get($this->settingsRegistry::ACCOUNTS_OPENING_PROCESSING_SETTING_NAME, 0);
            if (!$isAllowedAccountOpening) {
                return new CreateAccountResponse([
                    'status' => ExitStatus::ACCOUNT_OPENING_IS_BLOCKED,
                ]);
            }

            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $leaderAccount = $this->leaderAccountRepository->getLightAccountOrFail(new AccountNumber($createFollowerRequest->getLeaderAccountNumber()));

            if (!$leaderAccount->isFollowable()) {
                return new CreateAccountResponse([
                    'status' => ExitStatus::LEADER_ACCOUNT_NOT_FOLLOWABLE,
                ]);
            }
            if ($leaderAccount->isBlocked()) {
                return new CreateAccountResponse([
                    'status' => ExitStatus::LEADER_ACCOUNT_BLOCKED,
                ]);
            }
            if (!$leaderAccount->isActivated()) {
                return new CreateAccountResponse([
                    'status' => ExitStatus::LEADER_ACCOUNT_CLOSED,
                ]);
            }
            if ($leaderAccount->ownerId()->value() == $createFollowerRequest->getClientId()) {
                $followers = array_filter(
                    $this->followerAccountRepository->findOpenByLeaderAccountNumber($leaderAccount->number()),
                    function (FollowerAccount $account) use ($leaderAccount) {
                        return $account->ownerId()->isSameValueAs($leaderAccount->ownerId());
                    }
                );
                if (count($followers) > 0) {
                    return new CreateAccountResponse([
                        'status' => ExitStatus::SELF_FOLLOWING_LIMIT,
                    ]);
                }
            }

            $clientId = $createFollowerRequest->getClientId();
            $client = $this->clientGateway->fetchClientByClientId(new ClientId($clientId), $createFollowerRequest->getBroker());

            if (
                in_array($client->getParam('country_code'), $this->restrictedCountriesList) &&
                !$client->getCompany()->isAby()
            ) {
                return new CreateAccountResponse([
                    'status' => ExitStatus::COUNTRY_FORBIDDEN_TO_FOLLOW,
                ]);
            }

            if (Environment::isProd() && $client->getParam("company_id") == Company::ID_EU) {
                return new CreateAccountResponse([
                    'status' => ExitStatus::EU_CLIENT_FORBIDDEN_TO_FOLLOW,
                ]);
            }

            try {
                $leaderBroker = $this->brokerRepository->getByLeader($createFollowerRequest->getLeaderAccountNumber());
                $this->leverageService->validateFollowerLeverageAndCopyCoefficient(
                    new AccountNumber($createFollowerRequest->getLeaderAccountNumber()),
                    $leaderBroker,
                    new ClientId($createFollowerRequest->getClientId()),
                    $createFollowerRequest->getBroker(),
                    $createFollowerRequest->getCopyCoefficient()
                );
            } catch (IncompatibleAppropriatenessLeverage $e) {
                return new CreateAccountResponse([
                    'status' => ExitStatus::INCOMPATIBLE_APPROPRIATENESS_LEVERAGE,
                ]);
            } catch (IncompatibleMaxAllowedLeverage $e) {
                return new CreateAccountResponse([
                    'status' => ExitStatus::INCOMPATIBLE_MAX_ALLOWED_LEVERAGE,
                ]);
            } finally {
                $this->settingsRegistry->set(
                    "follower.incompatible_leverage_counter",
                    $this->settingsRegistry->get("follower.incompatible_leverage_counter") + 1
                );
            }

            $workflow = $this->workflowManager->newWorkflow(
                OpenAccountWorkflow::TYPE,
                new ContextData([
                    "leadAccNo" => $createFollowerRequest->getLeaderAccountNumber(),
                    "clientId" => $createFollowerRequest->getClientId(),
                    "copyCoef" => $createFollowerRequest->getCopyCoefficient(),
                    "stopLossPercent" => $createFollowerRequest->getProtectionLevel(),
                    ContextData::KEY_BROKER => $createFollowerRequest->getBroker(),
                ])
            );
            $this->workflowManager->processWorkflow($workflow);

            return new CreateAccountResponse([
                'status' => ExitStatus::SUCCESS,
                'result' => $workflow->getResult()->value(),
                'workflowId' => $workflow->id(),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation followersAccountNumberShortDataGet
     *
     * Returns investor account's short data
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return LightInvestorAccount
     *
     * @throws Exception
     */
    public function followersAccountNumberShortDataGet($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $result = $this
                ->followerAccountRepository
                ->getAsArray(intval($accountNumber));

            if($result == null) {
                $responseCode = 404;
                return null;
            }

            $result['leader'] = new LightStrategyManager($result);
            $result['leader']->setAccountNumber($result['leaderAccountNumber']);

            $accountNumber = $result['accountNumber'];
            $broker = $this->brokerRepository->getByFollower($accountNumber);

            $equity = $this->profitService->getLatestBalanceAndEquity($accountNumber, $broker)['equity'];

            $account = new LightInvestorAccount($result);
            $account->setEquity($equity);
            $account->setProfitTd($this->profitService->calculateTodayProfit($accountNumber, $equity));

            return $account;
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * @param string $accountNumber
     * @param RemittanceRestrictionsCheckRequest $request
     * @param int $responseCode
     * @param array $responseHeaders
     * @return RemittanceRestrictionsCheckStatus|null
     * @throws Exception
     */
    public function followersAccountNumberRestrictionsCheckPost($accountNumber, RemittanceRestrictionsCheckRequest $request, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            if (!$this->security->isGranted('ROLE_CLIENT')) {
                throw new AccessDeniedException();
            }

            $followerAccount = $this->followerAccountRepository->find(new AccountNumber($accountNumber));
            if (!$followerAccount) {
                $responseCode = 404;
                return null;
            }

            if ($request->getDirection() != RemittanceRestrictions::DIRECTION_TO) {
                return new RemittanceRestrictionsCheckStatus([
                    'allowed' => true,
                    'message' => null,
                    'code'    => null
                ]);
            }

            $leaderAccount = $this->leaderAccountRepository->find($followerAccount->leaderAccountNumber());

            if ($followerAccount->ownerId()->value() != $leaderAccount->ownerId()->value()) {
                return new RemittanceRestrictionsCheckStatus([
                    'allowed' => true,
                    'message' => null,
                    'code'    => null
                ]);
            }

            $selfFollowingLimit = $this->settingsRegistry->get('leader.self_following.deposit_limit', 0);

            if ($selfFollowingLimit > 0 && ($followerAccount->equity()->amount() + $request->getAmount()) > $selfFollowingLimit) {
                return new RemittanceRestrictionsCheckStatus([
                    'allowed' => false,
                    'message' => RemittanceRestrictions::RESTRICTION_MAX_EQUITY_MSG,
                    'code'    => RemittanceRestrictions::RESTRICTION_MAX_EQUITY_CODE
                ]);
            }

            return new RemittanceRestrictionsCheckStatus([
                'allowed' => true,
                'message' => null,
                'code' => null
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * @return string
     */
    public function getWorkerName()
    {
        return 'WEB[FOLLOWERS]';
    }

    /**
     * @param string $clientId
     * @param int $responseCode
     * @param array $responseHeaders
     *
     * @return InvestorAccountMinDeposit[]|null
     *
     * @throws Exception
     */
    public function clientClientIdInvestorAccountsMinDepositGet($clientId, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $results = $this
                ->followerAccountRepository
                ->getMinDepositsByClientId($clientId);

            return array_map(function (array $result) {
                return new InvestorAccountMinDeposit($result);
            }, $results);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * @param string $accountNumber
     * @param int $responseCode
     * @param array $responseHeaders
     *
     * @return InvestorAccountMinDeposit|null
     *
     * @throws Exception
     */
    public function followersAccountNumberMinDepositGet($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $result = $this
                ->followerAccountRepository
                ->getMinDeposit($accountNumber);

            if (empty($result)) {
                $responseCode = 404;
                return null;
            }

            return new InvestorAccountMinDeposit($result);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    public function followersAccountNumberLockPost($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $account = $this->followerAccountRepository->getLightAccountOrFail(new AccountNumber($accountNumber));
            $account->lockCopying();
            $this->followerAccountRepository->store($account);

            return new CreateAccountResponse([
                'status' => ExitStatus::SUCCESS,
                'result' => $account,
            ]);

        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    public function followersAccountNumberUnlockPost($accountNumber, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $account = $this->followerAccountRepository->getLightAccountOrFail(new AccountNumber($accountNumber));
            $account->lockCopying(false);
            $this->followerAccountRepository->store($account);

            return new CreateAccountResponse([
                'status' => ExitStatus::SUCCESS,
                'result' => $account,
            ]);

        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }
}
