<?php

namespace Fxtm\CopyTrading\Domain\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;

abstract class BaseFixture extends Fixture
{
    protected const LEADER_REFERENCE = 'leader_';

    public const TEST_LEADER_LOGIN = 12435;
    public const TEST_LEADER_ACC_NAME = 'leader';
    public const TEST_FOLLOWER_ACC_NO = 22222;
    public const TEST_POSITION = 12435;
    public const TEST_ACCOUNT_TYPE = 3;
    public const TEST_FRS_SERVER_ID = 41;
    public const TEST_BROKER = 'mt5';
    public const TEST_MT4_SYMBOL_TYPE = 5;
    public const TEST_INACTIVE_LEADER_LOGIN = 55555;

    abstract protected function getFixtureData(): array;
}
