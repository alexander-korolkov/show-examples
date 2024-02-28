<?php

namespace Fxtm\CopyTrading\Application\UniqueNameChecker;

use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\LeaderProfile\LeaderProfileRepository;
use Fxtm\CopyTrading\Interfaces\Repository\BrokerRepository;

class UniqueNameChecker
{
    /**
     * @var BrokerRepository
     */
    private $brokerRepository;

    /**
     * @var ClientGateway
     */
    private $clientGateway;

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var LeaderProfileRepository
     */
    private $leaderProfileRepository;

    /**
     * UniqueNameChecker constructor.
     * @param BrokerRepository $brokerRepository
     * @param ClientGateway $clientGateway
     * @param LeaderAccountRepository $leaderAccountRepository
     * @param LeaderProfileRepository $leaderProfileRepository
     */
    public function __construct(
        BrokerRepository $brokerRepository,
        ClientGateway $clientGateway,
        LeaderAccountRepository $leaderAccountRepository,
        LeaderProfileRepository $leaderProfileRepository
    ) {
        $this->brokerRepository = $brokerRepository;
        $this->clientGateway = $clientGateway;
        $this->leaderAccountRepository = $leaderAccountRepository;
        $this->leaderProfileRepository = $leaderProfileRepository;
    }


    /**
     * @param string $clientId
     * @param string $name
     * @return bool
     */
    public function isUniqueFullName($clientId, $name)
    {
        $broker = $this->brokerRepository->getByLeaderClientId($clientId);

        return $this->clientGateway->isUniqueFullname($name, $broker) && $this->isSatisfiedBy($name);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function isSatisfiedBy($name)
    {
        return $this->leaderAccountRepository->isUniqueAccountName($name)
            && $this->leaderProfileRepository->isUniqueNickname($name);
    }
}
