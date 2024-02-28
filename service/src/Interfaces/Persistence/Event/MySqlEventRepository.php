<?php


namespace Fxtm\CopyTrading\Interfaces\Persistence\Event;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Event\EventEntity;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Interfaces\Repository\EventException;
use Fxtm\CopyTrading\Interfaces\Repository\EventRepository;
use PDO;

/*
 * CREATE TABLE `events` (
 *	`occurred_at` DATETIME NOT NULL,
 *	`event_type` VARCHAR(25) NOT NULL,
 *	`account` BIGINT UNSIGNED NOT NULL,
 *	`workflow_id` BIGINT UNSIGNED NOT NULL,
 *	`message` VARCHAR(255) NOT NULL
 *)
 *COLLATE='utf8_general_ci'
 *;
 */
class MySqlEventRepository implements EventRepository
{


    /**
     * @var Connection
     */
    private $dbConnection;

    /**
     * @param Connection $dbConnection
     */
    public function setDbConnection(Connection $dbConnection): void
    {
        $this->dbConnection = $dbConnection;
    }

    function store(EventEntity $event) : void
    {
        return;
        try {
            /** @var Statement $stmt */
            $stmt = $this
                ->dbConnection
                ->prepare("
                    REPLACE INTO `events` (`occurred_at`, `event_type`, `account`, `workflow_id`, `message`)
                    VALUES (?, ?, ?, ?, ?) 
                ");

            $stmt->execute([
                $event->getTimeStamp()->format("Y-m-d H:i:s"),
                $event->getEventType(),
                $event->getAccountId(),
                $event->getWorkflowId(),
                $event->getMessage()
            ]);
        }
        catch (\Throwable $t) {
            throw new EventException("Failed to store event", 0, $t);
        }
    }

    function findByAccountAndType(AccountNumber $accountNumber, string $type): array
    {
        return [];
        try {
            /** @var Statement $stmt */
            $stmt = $this
                ->dbConnection
                ->prepare("
                    SELECT `occurred_at`, `event_type`, `account`, `workflow_id`, `message` 
                    FROM `events` 
                    WHERE `account` = ? AND `event_type` = ?
                ");

            $stmt->execute([
                $accountNumber->value(),
                $type
            ]);

            $result = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $eventEntity = new EventEntity();
                $eventEntity->setTimeStamp(DateTime::of($row['occurred_at']));
                $eventEntity->setEventType($row['event_type']);
                $eventEntity->setAccountId(intval($row['account']));
                $eventEntity->setWorkflowId(intval($row['workflow_id']));
                $eventEntity->setMessage($row['message']);
                $result[] = $eventEntity;
            }

            return $result;
        }
        catch (\Throwable $t) {
            throw new EventException("Failed to store event", 0, $t);
        }
    }

}