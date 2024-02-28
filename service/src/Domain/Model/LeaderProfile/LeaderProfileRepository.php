<?php

namespace Fxtm\CopyTrading\Domain\Model\LeaderProfile;

use Fxtm\CopyTrading\Domain\Model\Client\ClientId;

interface LeaderProfileRepository
{
    /**
     *
     * @param ClientId $id
     * @return LeaderProfile
     */
    public function find(ClientId $id);

    /**
     * @param ClientId $clientId
     * @return LeaderProfile
     */
    public function findOrNew(ClientId $clientId);

    /**
     *
     * @param LeaderProfile $profile
     * @return bool
     */
    public function store(LeaderProfile $profile);

    public function isUniqueNickname($nickname);
}
