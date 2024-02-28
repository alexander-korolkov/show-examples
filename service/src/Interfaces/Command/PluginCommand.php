<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Fxtm\CopyTrading\Application\Common\Environment;
use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\ContextData;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowManager;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use Fxtm\CopyTrading\Application\Follower\ResetStopLossLevelWorkflow;
use Fxtm\CopyTrading\Application\Follower\StopCopyingWorkflow;
use Fxtm\CopyTrading\Application\PluginGateway;
use Fxtm\CopyTrading\Application\PluginGatewayManager;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\CopyTrading\Domain\Model\Follower\FollowerAccountRepository;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use Fxtm\CopyTrading\Interfaces\Gateway\Plugin\PluginGatewayImpl;
use Fxtm\CopyTrading\Application\Follower\ResumeCopyingWorkflow;
use Fxtm\TwGateClient\TeamWoxService;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \Throwable;
use \Exception;

class PluginCommand extends Command
{

    use LockableTrait;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DataSourceFactory
     */
    private $factory;

    /**
     * @var PluginGatewayManager
     */
    private $pluginGatewayManager;

    /**
     * @var FollowerAccountRepository
     */
    private $followerAccountRepository;

    /**
     * @var WorkflowRepository
     */
    private $workflowRepository;

    /**
     * @var WorkflowManager
     */
    private $workflowManager;

    /**
     * @var TeamWoxService
     */
    private $teamwoxSvc;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param DataSourceFactory $factory
     */
    public function setConnectionFactory(DataSourceFactory $factory): void
    {
        $this->factory = $factory;
    }

    /**
     * @param PluginGatewayManager $pluginGatewayManager
     */
    public function setPluginGatewayManager(PluginGatewayManager $pluginGatewayManager): void
    {
        $this->pluginGatewayManager = $pluginGatewayManager;
    }

    /**
     * @param FollowerAccountRepository $followerAccountRepository
     */
    public function setFollowerAccountRepository(FollowerAccountRepository $followerAccountRepository): void
    {
        $this->followerAccountRepository = $followerAccountRepository;
    }

    /**
     * @param WorkflowRepository $workflowRepository
     */
    public function setWorkflowRepository(WorkflowRepository $workflowRepository): void
    {
        $this->workflowRepository = $workflowRepository;
    }

    /**
     * @param WorkflowManager $workflowManager
     */
    public function setWorkflowManager(WorkflowManager $workflowManager): void
    {
        $this->workflowManager = $workflowManager;
    }

    /**
     * @param TeamWoxService $teamwoxSvc
     */
    public function setTeamwoxSvc(TeamWoxService $teamwoxSvc): void
    {
        $this->teamwoxSvc = $teamwoxSvc;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        $this->setName('app:plugin')
            ->setDescription('')
            ->setHelp("DANGER!!! do not do anything if you aren't familiar to architecture of application");
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {

        if(!$this->lock()) {
            $output->writeln("Another process is running");
            $this->logger->error(
                self::fmt("Failed to set lock, another process is running")
            );
            return  -1;
        }

        $status = 0;
        try {

            $this->logger->info("Started");

            $this->postToTeamwox($this->processMessages($this->getPluginMessages()));

            $this->logger->info("Done");
        }
        catch (Throwable $exception) {

            $status = -1;

            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );

        }

        $this->release();

        return $status;
    }

    private function getPluginMessages() : array
    {
        try {

            $server = Server::MT5_FXTM;

            $connection = $this
                ->factory
                ->getPluginConnection($server);

            $stmt = $connection->prepare("
                  SELECT {$server} as server, q.* 
                  FROM plugin_msg_queue q 
                  WHERE q.init_by = 2 AND q.status NOT IN (?, ?)
                ");

            $stmt->execute([
                PluginGatewayImpl::STATUS_ME_ACKED,
                PluginGatewayImpl::STATUS_CANCELLED,
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (Exception $exception) {

            $this->logger->critical(
                self::fmt(
                    "Exception occurred: %s\n%s",
                    [$exception->getMessage(), $exception->getTraceAsString()]
                )
            );

        }

        return [];
    }

    private function processMessages(array $messages) : array
    {
        $this->logger->info(self::fmt("Messages received %d.", [count($messages)]));

        $tobePosted = [];

        foreach ($messages as $msg)
        {
            $this->logger->info(self::fmt(
                "#%d (server: %d) - %d [%s]",
                [$msg["id"], $msg["server"], $msg["msg_type"], $msg["payload"]]
            ));

            try {

                $this
                    ->pluginGatewayManager
                    ->getForServer($msg["server"])
                    ->acknowledgeMessage($msg["id"]);

                if ($msg["result"] > PluginGatewayImpl::RESULT_SUCCESS) {
                    $this->logger->info(self::fmt("Error %d - %s", [$msg["result"], $msg['comment']]));
                    continue;
                }

                if (!($follAcc = $this->followerAccountRepository->getLightAccount(new AccountNumber($msg["acc_no"])))) {
                    $this->logger->info(self::fmt("Account #%d not found", [$msg["acc_no"]]));
                    continue;
                }

                if ($msg["msg_type"] == PluginGatewayImpl::$msgTypes[PluginGateway::FOLLOWER_COPYING]) {

                    // this is peezdec'
                    preg_match("/^\[(\d+)\].*/", $msg["comment"], $matches);
                    $errCode = isset($matches[1]) ? $matches[1] : null;

                    if ($errCode == 1) {
                        $reason = StopCopyingWorkflow::REASON_PROTECTION_LEVEL;
                    } else if (in_array($errCode, [2, 4])) {
                        $reason = StopCopyingWorkflow::REASON_INSUFFICIENT_FUNDS;
                    } else {
                        $reason = StopCopyingWorkflow::REASON_UNKNOWN;
                    }

                    if (strpos($msg['comment'], '{Join failed}') !== false) {
                        $hasProcessingResumeWorkflow = !empty(
                        array_filter(
                            $this->workflowRepository->findByCorrelationIdAndType($msg["acc_no"], ResumeCopyingWorkflow::TYPE),
                            function (AbstractWorkflow $workflow) {
                                return $workflow->getState() == WorkflowState::PROCEEDING;
                            }
                        )
                        );

                        if ($hasProcessingResumeWorkflow) {
                            $this->logger->info(self::fmt("Skipped because there is processing resume-copying workflow"));
                            continue;
                        }
                    }

                    $alreadySubmitted = !empty(
                    array_filter(
                        $this->workflowRepository->findByCorrelationIdAndType($msg["acc_no"], StopCopyingWorkflow::TYPE),
                        function (AbstractWorkflow $workflow) use ($msg) {
                            return $msg["id"] == $workflow->getContext()->getIfHas("msgId");
                        }
                    )
                    );

                    if ($alreadySubmitted) {
                        $this->logger->info(self::fmt("Already submitted"));
                        continue;
                    }

                    $workflow = $this
                        ->workflowManager
                        ->newWorkflow(
                            StopCopyingWorkflow::TYPE,
                            new ContextData([
                                "accNo"   => $msg["acc_no"],
                                "reason"  => $reason,
                                "msgId"   => $msg["id"],
                                "comment" => $msg["comment"],
                                ContextData::KEY_BROKER => $follAcc->broker(),
                            ])
                        );

                    $this
                        ->workflowManager
                        ->enqueueWorkflow($workflow);
                    $this->logger->info(self::fmt("Workflow #%d created", [$workflow->id()]));

                } elseif ($msg["msg_type"] == PluginGatewayImpl::$msgTypes[PluginGateway::FOLLOWER_STOPLOSS] && $msg["status"] == PluginGatewayImpl::STATUS_PLUGIN_ACKED) {

                    list($execEq, $isCopyingStopped) = explode(';', $msg["payload"]);

                    $workflow = $this
                        ->workflowManager
                        ->newWorkflow(
                            ResetStopLossLevelWorkflow::TYPE,
                            new ContextData([
                                "accNo" => $msg["acc_no"],
                                "msgId" => $msg["id"],
                                "stopLossEquityTriggered" => $execEq,
                                ContextData::KEY_BROKER => $follAcc->broker(),
                            ])
                        );

                    $this
                        ->workflowManager
                        ->enqueueWorkflow($workflow);
                    $this->logger->info(self::fmt("Workflow #%d created", [$workflow->id()]));

                    $protectedEq = $this
                        ->factory
                        ->getCTConnection()
                        ->executeQuery("SELECT stoploss_equity FROM follower_accounts WHERE acc_no = {$msg["acc_no"]}")
                        ->fetchOne();

                    if(intval($execEq * 10000.0) == intval($protectedEq * 10000.0) || intval($protectedEq * 10000.0) == 0) {
                        $diff = '0.00';
                    }
                    else {
                        $diff = sprintf("%.2f", ($protectedEq - $execEq) / $protectedEq);
                    }

                    $twParams = [
                        "acc_no"           => $msg["acc_no"],
                        "protected_equity" => $protectedEq,
                        "exec_equity"      => $execEq,
                        "difference"       => $diff,
                        "copying_stopped"  => $isCopyingStopped ? "YES" : "NO",
                        "workflow_id"      => $workflow->id(),
                    ];

                    $tobePosted[] = $twParams;

                } else {
                    $this->logger->info(self::fmt("Handler not found"));
                }

            }
            catch (Throwable $exception) {

                $exepClass = get_class($exception);
                $message = "Failed [{$exepClass}: {$exception->getMessage()}]\n";

                $this
                    ->pluginGatewayManager
                    ->getForServer($msg["server"])
                    ->messageFailed($msg["id"], $message);

                $this->logger->critical(
                    self::fmt(
                        "Exception occurred: %s\n%s",
                        [$exception->getMessage(), $exception->getTraceAsString()]
                    )
                );

            }

        }

        return $tobePosted;
    }

    private function postToTeamwox(array $messages) : void
    {

        if (Environment::isTestUname() || Environment::isStaging()) {
            return;
        }

        if (count($messages) == 0) {
            return;
        }

        $rows = '';

        foreach ($messages as $params)
        {
            $rows  .= "
                    <tr>
                      <td>{$params["acc_no"]}</td>
                      <td>{$params["protected_equity"]}</td>
                      <td>{$params["exec_equity"]}</td>
                      <td>{$params["difference"]}</td>
                      <td>{$params["copying_stopped"]}</td>
                      <td>{$params["workflow_id"]}</td>
                    </tr>
            ";
        }

        $comment = "
            <table class='standart'>
              <thead>
                <tr>
                  <th style='text-align: center;'>Acc. No.</th>
                  <th style='text-align: center;'>Protected Equity</th>
                  <th style='text-align: center;'>Equity at Execution</th>
                  <th style='text-align: center;'>Difference, %</th>
                  <th style='text-align: center;'>Copying Stopped</th>
                  <th style='text-align: center;'>Workflow ID</th>
                </tr>
              </thead>
              <tbody>
              {$rows}
              </tbody>
            </table>
        ";

        $this
            ->teamwoxSvc
            ->getTask(13300)
            ->addComment($comment)
            ->save();
    }

    private static function fmt(string $msg, array $params = []) : string
    {
        return sprintf("[CMD %s]: %s", self::class, vsprintf($msg, $params));
    }

}