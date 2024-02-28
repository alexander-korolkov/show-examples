<?php

namespace Fxtm\CopyTrading\Domain\Model\Client;

interface ClientRepository
{
    public function store(Client $client);
    public function find(ClientId $id);
}
