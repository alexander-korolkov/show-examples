<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Domain\Entity\User;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class CreateUserCommand extends Command
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var UserPasswordEncoderInterface
     */
    private $encoder;

    /**
     * CreateUserCommand constructor.
     * @param ObjectManager $objectManager
     * @param UserPasswordEncoderInterface $encoder
     */
    public function __construct(
        ObjectManager $objectManager,
        UserPasswordEncoderInterface $encoder
    ) {
        $this->objectManager = $objectManager;
        $this->encoder = $encoder;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:user:create')
            ->setAliases(['a:u:c'])
            ->setDescription('Creates a new user.')
            ->setHelp('Creates a new user for jwt authentication.')
            ->addArgument('username', InputArgument::REQUIRED, 'User name')
            ->addArgument('password', InputArgument::REQUIRED, 'User password')
            ->addArgument('role', InputArgument::REQUIRED, 'User role');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Creating new user...');

        $username = $input->getArgument('username');
        $password = $input->getArgument('password');
        $role = $input->getArgument('role');

        $user = new User();
        $user->fill($username, $this->encoder->encodePassword($user, $password), $role);

        $this->objectManager->persist($user);
        $this->objectManager->flush();

        $output->writeln('Done.');
    }
}
