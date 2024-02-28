<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Client;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Domain\Common\Objects;
use Fxtm\CopyTrading\Domain\Model\Client\Client;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Client\ClientRepository;
use PDO;

class MySqlClientRepository implements ClientRepository
{
    protected $dbConn = null;
    protected $clientGateway = null;
    protected static $cache = [];

    public function __construct(Connection $dbConn, ClientGateway $clientGateway)
    {
        $this->dbConn = $dbConn;
        $this->clientGateway = $clientGateway;
    }

    public function find(ClientId $id)
    {
        $id = $id->value();
        if (empty(self::$cache[$id])) {
            $stmt = $this->dbConn->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            if (!empty($client = $stmt->fetch(PDO::FETCH_ASSOC))) {
                self::$cache[$id] = $this->mapFromGateway($this->mapFromArray($client));
            } else {
                return null;
            }
        }
        return self::$cache[$id];
    }

    public function store(Client $client)
    {
        $stmt = $this->dbConn->prepare("
            INSERT INTO clients (
                `id`,
                `quest_attempt_id`
            ) VALUES (
                :id,
                :quest_attempt_id
            ) ON DUPLICATE KEY UPDATE `quest_attempt_id` = VALUES(`quest_attempt_id`)
        ");
        $stmt->execute($client->toArray());
        self::$cache[$this->dbConn->lastInsertId()] = $client;
    }

    protected function mapFromArray(array $row)
    {
        return Objects::newInstance(Client::CLASS, $row);
    }

    protected function mapFromGateway(Client $client)
    {
        //$client = $this->clientGateway->fetchClientByClientId($client->id());
        return $client;
    }
}
