<?php


namespace Fxtm\CopyTrading\Interfaces\Command;


use Fxtm\CopyTrading\Application\TradeOrderGatewayFacade;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Server;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class EquitiesOrdersCommand extends Command
{

    use LockableTrait;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DataSourceFactory
     */
    private $dataSource;

    /** @var TradeOrderGatewayFacade */
    private $tradeOrderGatewayFacade;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param DataSourceFactory $dataSourceFactory
     */
    public function setDataSource(DataSourceFactory $dataSourceFactory): void
    {
        $this->dataSource = $dataSourceFactory;
    }

    /**
     * @param TradeOrderGatewayFacade $gatewayFacade
     */
    public function setTradeOrderGateway(TradeOrderGatewayFacade $gatewayFacade): void
    {
        $this->tradeOrderGatewayFacade = $gatewayFacade;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:equities_orders')
            ->setDescription('')
            ->setHelp('');
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        if (!$this->lock($this->getName())) {
            $output->writeln("Another process is running");
            $this->logger->error("Failed to set lock, another process is running");
            return -1;
        }

        $this->logger->info('EquitiesOrdersCommand started.');
        if (DateTime::NOW()->prevHour()->isWeekend()) {
            $this->logger->info("[equity_orders] Previous hour is weekend. Exiting.");
            $this->release();
            return 0;
        }

        $accounts = $this->getLeadersAccounts();
        $this->logger->info(sprintf("[equity_orders] Checking %d accounts.", count($accounts)));
        foreach ($accounts as $accNo => $server) {

            if (empty($orders = $this->lookupOrders($server, $accNo))) {
                continue;
            }

            $equity = $this->tradeOrderGatewayFacade
                ->getForServer($server)
                ->getAccountEquity(new AccountNumber($accNo));

            if (empty($equity) && is_null($equity)) {
                $this->logger->warning(sprintf("[equity_orders] %d... error: couldn't retrieve equity\n", $accNo));
                continue;
            }

            foreach ($orders as $order) {

                if ($this->isOrderRecorded($order['id'], $accNo, $order['date_time'], $order['equity_diff'])) {
                    continue;
                }

                try {
                    $this->insertEquityValue($order['acc_no'], $order['date_time'], $equity, $order['equity_diff'], $order['id']);
                    $equity -= $order["equity_diff"];
                    $this->logger->info(
                        sprintf("[equity_orders] %d... %s, %.4f, %.4f, %d\n", $order['acc_no'], $order['date_time'], $equity, $order['equity_diff'], $order['id'])
                    );
                } catch (\Exception $e) {
                    $this->logger->error(
                        sprintf("[equity_orders] %d... error: %s\n", $order["acc_no"], $e->getMessage())
                    );
                }
            }
        }

        $this->logger->info('EquitiesOrdersCommand finished.');
        $this->release();
        return 0;
    }

    private function getLeadersAccounts(): array
    {
        return $this
            ->dataSource
            ->getCTConnection()
            ->query("SELECT acc_no, server FROM leader_accounts WHERE status = 1")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    private function lookupOrders(int $server, int $accNo): array
    {
        $connectionFRS = $this->dataSource->getFrsConnection($server);

        if (Server::GetPlatformType($server) == Server::PLATFORM_TYPE_MT4) {
            $sql = "
                SELECT
                    tr.`order` AS id,
                    tr.`login` AS acc_no,
                    tr.`cmd` AS type,
                    tr.`profit` AS equity_diff,
                    FROM_UNIXTIME(IF(tr.`cmd` = 6, tr.`close_time`, tr.`open_time`)) AS date_time
                FROM mt4_trade_record AS tr
                WHERE tr.`login` = ? AND ((tr.`cmd` = 6 AND tr.`close_time` != 0) OR tr.`cmd` = 7) AND tr.`comment` NOT LIKE 'Summary trade result%'
                HAVING date_time BETWEEN NOW() - INTERVAL 6 MINUTE AND NOW()
                ORDER BY date_time DESC, tr.`order` DESC
            ";
        } else {
            $sql = "
                SELECT
                    d.`Deal` AS id,
                    d.`Login` AS acc_no,
                    d.`Action` AS type,
                    d.`Profit` AS equity_diff,
                    d.`Time` AS date_time
                FROM mt5_deal AS d
                WHERE d.`Login` = ? AND d.`Action` IN (3, 6)
                HAVING date_time BETWEEN NOW() - INTERVAL 6 MINUTE AND NOW()
                ORDER BY date_time DESC, d.`Deal` DESC
            ";
        }

        $stmt = $connectionFRS->prepare($sql);
        $stmt->execute([$accNo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function insertEquityValue($accNo, $dt, $equity, $inOut, $order): void
    {

        $dbConn = $this->dataSource
            ->getCTConnection();

        $dbConn->beginTransaction();

        try {
            $dbConn->prepare("INSERT INTO equities (acc_no, date_time, equity, in_out) VALUES (:acc_no, :date_time, :equity, :in_out);")
                ->execute([
                    "acc_no" => $accNo,
                    "date_time" => $dt,
                    "equity" => $equity,
                    "in_out" => $inOut,
                ]);
            $dbConn->prepare("INSERT INTO equities_orders (equity_id, order_id) VALUES (LAST_INSERT_ID(), :order_id);")
                ->execute(["order_id" => $order]);

            //Update Net as well
            $dbConn->prepare("UPDATE leader_accounts SET balance = balance + :in_out WHERE acc_no=:acc_no")
                ->execute([
                    "in_out" => $inOut,
                    "acc_no" => $accNo,
                ]);

            $dbConn->commit();
        } catch (\Exception $e) {
            $dbConn->rollBack();
            throw $e;
        }
    }

    private function isOrderRecorded($order, $account, $orderTime, $amount): bool
    {
        static $stmt = null;

        if (empty($stmt)) {
            $stmt = $this->dataSource
                ->getCTConnection()
                ->prepare("SELECT EXISTS(SELECT * FROM equities_orders WHERE order_id = ?)");
        }

        $stmt->execute([$order]);
        $result = intval($stmt->fetchColumn()) != 0;
        $stmt->closeCursor();

        if ($result){
            // Explicit order recorded to CT-DB
            return true;
        }

        // Check if there are recorded any balance operations w/o order number AFTER order been processed
        static $stmtImplicit = null;

        if(empty($stmtImplicit)) {
            $stmtImplicit = $this->dataSource
                ->getCTConnection()
                ->prepare("
                    SELECT 
                        e.`in_out` 
                    FROM `equities` AS e
                    LEFT OUTER JOIN `equities_orders` AS eo ON e.id = eo.equity_id
                    WHERE 
                        e.`acc_no` = ? AND 
                        e.`date_time` >= ? AND 
                        ABS(e.`in_out`) > 0 AND 
                        eo.`order_id` IS NULL            
            ");
        }

        $stmtImplicit->execute([$account, $orderTime]);
        $result = $stmtImplicit->fetchColumn();
        $stmtImplicit->closeCursor();

        if($result === false) {
            // No even hint to find recorder order
            return false;
        }

        $result = floatval($result);
        $amount = floatval($amount);

        // Make sure amounts have same sign
        // Different sign means that the order was not recorded
        if($result > 0.0 && $amount > 0.0) {
            return ($result - $amount) > -0.001;
        }

        // On withdrawal $result should be less or equal to amount
        if($result < 0.0 && $amount < 0.0) {
            return ($amount - $result) > -0.001;
        }

        return false;
    }
}
