<?php

namespace Fxtm\CopyTrading\Domain\DataFixtures;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Currency;
use Fxtm\CopyTrading\Tests\Entities\FollowerAccountForTests;

class FollowerAccountForTestsFixture extends BaseFixture implements DependentFixtureInterface
{
    private const KEY_ACC_NUMBER = 'acc_number';
    private const KEY_BROKER = 'broker';
    private const KEY_SERVER_ID = 'server_id';
    private const KEY_OWNER_ID = 'owner_id';
    private const KEY_LEADER_ACC = 'leader_acc';
    private const KEY_SENDER = 'sender';

    public function load(ObjectManager $manager)
    {
        foreach ($this->getFixtureData() as $elem) {
            $followerAccount = new FollowerAccountForTests(
                $elem[self::KEY_ACC_NUMBER],
                $elem[self::KEY_BROKER],
                $elem[self::KEY_SERVER_ID],
                $elem[self::KEY_OWNER_ID],
                $elem[self::KEY_LEADER_ACC],
                $elem[self::KEY_SENDER]
            );
            $followerAccount->setAccNo($elem[self::KEY_ACC_NUMBER]->value());
            $followerAccount->setBroker($elem[self::KEY_BROKER]);
            $followerAccount->setServer($elem[self::KEY_SERVER_ID]);
            $followerAccount->setAccCurr(Currency::USD());
            $followerAccount->setOwnerId($elem[self::KEY_ACC_NUMBER]->value());
            $followerAccount->setLeadAccNo($elem[self::KEY_LEADER_ACC]);
            $followerAccount->setOpenedAt(new \DateTime());
            $followerAccount->setSettledAt(new \DateTime());
            $followerAccount->setNextPayoutAt(new \DateTime('+ 5 days'));

            $manager->persist($followerAccount);
        }
        $manager->flush();
    }

    protected function getFixtureData(): array
    {
        return [
            [
                self::KEY_ACC_NUMBER => new AccountNumber(self::TEST_FOLLOWER_ACC_NO),
                self::KEY_BROKER => self::TEST_BROKER,
                self::KEY_SERVER_ID => self::TEST_FRS_SERVER_ID,
                self::KEY_OWNER_ID => new ClientId(self::TEST_FOLLOWER_ACC_NO),
                self::KEY_LEADER_ACC => $this->getReference(self::LEADER_REFERENCE . self::TEST_LEADER_LOGIN),
                self::KEY_SENDER => new ClientId(self::TEST_FOLLOWER_ACC_NO),
            ],
        ];
    }

    public function getDependencies(): array
    {
        return [
            LeaderAccountForTestsFixture::class
        ];
    }
}
