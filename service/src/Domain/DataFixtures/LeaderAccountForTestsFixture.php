<?php

namespace Fxtm\CopyTrading\Domain\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Tests\Entities\LeaderAccountForTests;

class LeaderAccountForTestsFixture extends BaseFixture
{
    private const KEY_ACC_NUMBER = 'acc_number';
    private const KEY_BROKER = 'broker';
    private const KEY_ACCOUNT_TYPE = 'account_type';
    private const KEY_SERVER_ID = 'server_id';
    private const KEY_CURRENCY = 'currency';
    private const KEY_OWNER_ID = 'owner_id';
    private const KEY_ACC_NAME = 'acc_name';
    private const KEY_SENDER = 'sender';

    public function load(ObjectManager $manager)
    {
        foreach ($this->getFixtureData() as $elem) {
            $leaderAccount = new LeaderAccountForTests(
                $elem[self::KEY_ACC_NUMBER],
                $elem[self::KEY_BROKER],
                $elem[self::KEY_ACCOUNT_TYPE],
                $elem[self::KEY_SERVER_ID],
                $elem[self::KEY_CURRENCY],
                $elem[self::KEY_OWNER_ID],
                $elem[self::KEY_ACC_NAME],
                $elem[self::KEY_SENDER]
            );
            $leaderAccount->setAccNo($elem[self::KEY_ACC_NUMBER]->value());
            $leaderAccount->setServer($elem[self::KEY_SERVER_ID]);
            $leaderAccount->setAccCurr((string)$elem[self::KEY_CURRENCY]);
            $leaderAccount->setOwnerId($elem[self::KEY_OWNER_ID]->value());
            $leaderAccount->setAccName($elem[self::KEY_ACC_NAME]);
            $leaderAccount->setOpenedAt(new \DateTime());
            $leaderAccount->setBroker($elem[self::KEY_BROKER]);

            $this->addReference(self::LEADER_REFERENCE . (string)$elem[self::KEY_ACC_NUMBER], $leaderAccount);
            $manager->persist($leaderAccount);
        }
        $manager->flush();
    }

    protected function getFixtureData(): array
    {
        return [
            [
                self::KEY_ACC_NUMBER => new AccountNumber(self::TEST_LEADER_LOGIN),
                self::KEY_BROKER => self::TEST_BROKER,
                self::KEY_ACCOUNT_TYPE => self::TEST_ACCOUNT_TYPE,
                self::KEY_SERVER_ID => self::TEST_FRS_SERVER_ID,
                self::KEY_CURRENCY => Currency::USD(),
                self::KEY_OWNER_ID => new ClientId(self::TEST_LEADER_LOGIN),
                self::KEY_ACC_NAME => self::TEST_LEADER_ACC_NAME,
                self::KEY_SENDER => new AccountNumber(self::TEST_LEADER_LOGIN),
            ],
        ];
    }
}
