<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Application\Censorship\Censor;
use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Application\UniqueNameChecker\UniqueNameChecker;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\LeaderProfile\LeaderProfileRepository;

class AccountNameService
{
    private $clientGateway = null;

    /**
     * @var LeaderAccountRepository
     */
    private $leadAccRepo;

    private $profRepo = null;

    /**
     * @var Censor
     */
    private $censor;

    /**
     * @var UniqueNameChecker
     */
    private $uniqueNameChecker;

    public function __construct(
        ClientGateway $clientGateway,
        LeaderAccountRepository $leadAccRepo,
        LeaderProfileRepository $profRepo,
        Censor $censor,
        UniqueNameChecker $uniqueNameChecker
    ) {
        $this->clientGateway = $clientGateway;
        $this->leadAccRepo = $leadAccRepo;
        $this->profRepo = $profRepo;
        $this->censor = $censor;
        $this->uniqueNameChecker = $uniqueNameChecker;
    }

    public function generateUniqueNameForClient(ClientId $clientId, $broker)
    {
        return $this->generateUniqueNameFromEmail(
            $this->clientGateway->fetchClientByClientId($clientId, $broker)->getParam("email")
        );
    }

    public function generateUniqueNameFromEmail($email)
    {
        $name = substr($email, 0, strpos($email, "@"));
        $name = $this->censor->replace($name);
        $name = preg_replace("/[^a-z0-9_\-]/i", "", $name);
        $name = str_pad($name, 3, "_");
        $name = substr($name, 0, 15);

        $c = 2;
        $accName = $name;
        while (!$this->uniqueNameChecker->isSatisfiedBy($accName)) {
            $accName = substr($name, 0, 15 - strlen($c)) . $c;
            $c++;
        }

        return $accName;
    }

    public function isUniqueName($name)
    {
        return $this->uniqueNameChecker->isSatisfiedBy($name);
    }
}
