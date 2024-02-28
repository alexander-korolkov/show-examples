<?php

namespace Fxtm\CopyTrading\Interfaces\DAO\Account;

use ClickHouseDB\Client;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Account\AccountCandle;
use RuntimeException;

class AccountCandleDaoMT4ClickHouseImpl implements AccountCandleDao
{

    const CHUNK_SIZE = 500;

    /**
     * @var Client
     */
    private $chClient;

    /**
     * @var string
     */
    private $dbName;

    /**
     * @var int
     */
    private $frsServerId;


    /**
     * AccountCandleDaoMT4ClickHouseImpl constructor.
     * @param Client $chClient
     * @param int $serverId
     * @param string $dbName
     */
    public function __construct(Client $chClient, int $serverId, string $dbName)
    {
        $this->chClient     = $chClient;
        $this->frsServerId  = $serverId;
        $this->dbName       = $dbName;
    }


    /**
     * @inheritDoc
     */
    public function get(int $login): AccountCandle
    {
        if (!$this->chClient->ping()) {
            throw new RuntimeException("ClickHouse DB is unavailable.");
        }

        $stmt = $this
            ->chClient
            ->select(
                "
                    SELECT 
                        ac.`login`, ac.`equity_close`
                    FROM `{$this->dbName}`.`mt4_account_candles_history` AS ac
                    WHERE 
                        ac.`frs_ServerID` = :frs_server_id AND ac.`login` = :login 
                    ORDER BY ac.`frs_Timestamp` DESC 
                    LIMIT 1
	            ",
                [
                    'frs_server_id' => $this->frsServerId,
                    'login'         => $login,
                ]
            );

        $res = $stmt->fetchOne();

        return $res
            ? new AccountCandle($res['login'], $res['equity_close'])
            : new AccountCandle($login, 0);
    }

    /**
     * @inheritDoc
     */
    public function getMany(array $logins, DateTime $onDatetime): array
    {
        if (!$this->chClient->ping()) {
            throw new RuntimeException("ClickHouse DB is unavailable.");
        }

        $res = [];
        $numberOfLogins = count($logins);

        for($sliceIndex = 0; $sliceIndex < $numberOfLogins; $sliceIndex += self::CHUNK_SIZE) {
            $slice = array_slice($logins, $sliceIndex, self::CHUNK_SIZE);
            $stmt = $this
                ->chClient
                ->select(
                    "
                    SELECT 
                        ac.`login`, argMax(ac.`equity_close`, ac.`candletime`) as equity_close
                    FROM `{$this->dbName}`.`mt4_account_candles_history` AS ac
                    WHERE 
                        ac.`frs_ServerID` = :frs_server_id AND 
                        ac.`login` IN (" . implode(',', $slice) . ") AND 
                        ac.`frs_Timestamp` <= toUnixTimestamp(:datetime) * 1000
                    GROUP BY ac.`login`
	            ",
                    [
                        'frs_server_id' => $this->frsServerId,
                        'datetime' => $onDatetime,
                    ]
                );

            foreach ($stmt->rows() as $row) {
                $res[$row['login']] = new AccountCandle($row['login'], $row['equity_close']);
            }
        }

        return $res;
    }

}
