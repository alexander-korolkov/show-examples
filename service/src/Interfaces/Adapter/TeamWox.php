<?php


namespace Fxtm\CopyTrading\Interfaces\Adapter;


use Fxtm\TwGateClient\API\TeamWoxServiceClient;
use Fxtm\TwGateClient\TeamWoxService;

class TeamWox
{

    /**
     * @var string TeamWox service URL
     */
    private $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function __invoke()
    {
        return new TeamWoxService(new TeamWoxServiceClient($this->url));
    }

}