<?php

namespace Fxtm\CopyTrading\Application;

use Fxtm\CopyTrading\Domain\Model\Client\Client;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;

interface ClientGateway
{
    /**
     * @param ClientId $clientId
     * @param string $broker
     * @return Client
     */
    public function fetchClientByClientId(ClientId $clientId, $broker);

    /**
     * @param ClientId $clientId
     * @param string $broker
     * @return array
     * @throws \Exception
     */
    public function getAppropriatenessTestAnswers(ClientId $clientId, $broker);

    /**
     * @param ClientId $clientId
     * @param string $broker
     * @return float
     */
    public function getPointsKnowledgeAppropriatenessTest(ClientId $clientId, $broker);

    /**
     * @param string $name
     * @param string $broker
     * @return bool
     */
    public function isUniqueFullname($name, $broker);

    /**
     * @param ClientId $clientId
     * @param string $broker
     * @return bool
     */
    public function clientInInactiveStatus(ClientId $clientId, $broker);
}
