<?php

namespace Fxtm\CopyTrading\Application\Services\Technical;

use Exception;
use Fxtm\CopyTrading\Application\EquityService;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;
use Fxtm\CopyTrading\Interfaces\Controller\ExitStatus;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeBalanceRequest;
use Psr\Log\LoggerInterface as Logger;
use Fxtm\CopyTrading\Application\Metrix\MetrixData;
use Fxtm\CopyTrading\Application\Security\Role;
use Fxtm\CopyTrading\Application\Services\LoggerTrait;
use Fxtm\CopyTrading\Application\Services\SecurityTrait;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Server\Generated\Model\Settings;
use Fxtm\CopyTrading\Server\Generated\Api\TechnicalApiInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Fxtm\CopyTrading\Server\Generated\Model\ActionResult;

class TechnicalService implements TechnicalApiInterface
{

    use LoggerTrait, SecurityTrait;

    /**
     * @var SettingsRegistry
     */
    private $settingsRegistry;

    /**
     * @var EquityService
     */
    private $equityService;

    /**
     * @var FollowerAccountRepository
     */
    private $followersRepository;

    /**
     * @var LeaderAccountRepository
     */
    private $leadersRepository;

    /**
     * TechnicalService constructor.
     * @param SettingsRegistry $settingsRegistry
     * @param EquityService $equityService
     * @param FollowerAccountRepository $followersRepository
     * @param LeaderAccountRepository $leadersRepository
     * @param Security $security
     * @param Logger $logger
     */
    public function __construct(
        SettingsRegistry $settingsRegistry,
        EquityService $equityService,
        FollowerAccountRepository $followersRepository,
        LeaderAccountRepository $leadersRepository,
        Security $security,
        Logger $logger
    )
    {
        $this->settingsRegistry = $settingsRegistry;
        $this->equityService = $equityService;
        $this->followersRepository = $followersRepository;
        $this->leadersRepository = $leadersRepository;
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
    public function setjwt($value) {}

    /**
     * Operation settingsGet
     *
     * Get copy-trading settings
     *
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return Settings
     *
     * @throws Exception
     */
    public function settingsGet(&$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $settings = $this->settingsRegistry->getAll();
            return $this->convertToModel($settings);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * @param array $settings
     * @return Settings
     */
    private function convertToModel(array $settings)
    {
        return new Settings([
            'leaderHideInactiveDaysThreshold' => $settings['leader.hide_inactive_days_threshold'],
            'leaderMinEquityEcn' => $settings['leader.min_equity.ecn'],
            'leaderMinEquityAiEcn' => $settings['leader.min_equity.ai_ecn'],
            'leaderMinEquityAdvantageEcn' => $settings['leader.min_equity.advantage_ecn'],
            'followerAccMinEquity' => $settings['follower_acc.min_equity'],
            'leaderHideProfitThreshold' => $settings['leader.hide_profit_threshold'],
            'leaderMinEquityEcnZero' => $settings['leader.min_equity.ecn_zero'],
            'leaderAccMinEquity' => $settings['leader_acc.min_equity'],
            'followerMinEquity' => $settings['follower.min_equity'],
        ]);
    }

    /**
     * @return string
     */
    public function getWorkerName()
    {
        return 'WEB[TECHNICAL]';
    }

    public function balanceOperationPut(ChangeBalanceRequest $changeBalanceRequest = null, &$responseCode, array &$responseHeaders)
    {
        try {

            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $amount = new Money($changeBalanceRequest->getAmount(), Currency::USD());
            $equity = new Money($changeBalanceRequest->getEquity(), Currency::USD());

            $account = new AccountNumber($changeBalanceRequest->getAccount());
            $follower = $this->followersRepository->getLightAccount($account);
            if($follower != null) {
                // Do not collect equities if not activated
                if(!$follower->isActivated()) {
                    return new ActionResult([
                        'status' => ExitStatus::SUCCESS,
                        'result' => false,
                    ]);
                }
            } else {
                $leader = $this->leadersRepository->getLightAccount($account);
                if($leader == null) {

                    $this->logger->warning(
                        sprintf(
                            "Trading account '%s' was not found nor in followers table nor in leaders",
                            $account
                        )
                    );
                    // account was not found
                    return new ActionResult([
                        'status' => ExitStatus::SUCCESS,
                        'result' => false,
                    ]);
                }
                // Do not collect equities if not activated
                if(!$leader->isActivated()) {
                    return new ActionResult([
                        'status' => ExitStatus::SUCCESS,
                        'result' => false,
                    ]);
                }
            }

            $equity = $equity->add($amount);

            $this->equityService->saveTransactionEquityChange(
                $account,
                $equity,
                $amount,
                $changeBalanceRequest->getOrder(),
                (new DateTime())->setTimestamp($changeBalanceRequest->getOrderTime())
            );

            return new ActionResult([
                'status' => ExitStatus::SUCCESS,
                'result' => true,
            ]);

        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        }
        catch (\Throwable $any) {

            $this->logger->error(
                sprintf(
                    "Exception '%s' with message '%s' in %s on line %d. Trace: %s",
                    get_class($any),
                    $any->getMessage(),
                    $any->getFile(),
                    $any->getLine(),
                    $any->getTraceAsString()
                )
            );

            return new ActionResult([
                'status' => ExitStatus::SUCCESS,
                'result' => false,
            ]);

        }

    }

}
