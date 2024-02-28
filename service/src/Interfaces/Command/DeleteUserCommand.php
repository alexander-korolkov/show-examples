<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Fxtm\CopyTrading\Interfaces\Controller\ValidationException;
use Fxtm\CopyTrading\Interfaces\Repository\UserRepository;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteUserCommand extends Command
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * CreateUserCommand constructor.
     * @param ObjectManager $objectManager
     * @param UserRepository $userRepository
     */
    public function __construct(
        ObjectManager $objectManager,
        UserRepository $userRepository
    ) {
        $this->objectManager = $objectManager;
        $this->userRepository = $userRepository;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:user:delete')
            ->setDescription('Deletes user.')
            ->setHelp('Permanently deletes user.')
            ->addArgument('username', InputArgument::REQUIRED, 'User name');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');

        $user = $this->userRepository->getByUsername($username);
        if (!$user) {
            throw new ValidationException('Invalid login!');
        }

        $this->objectManager->remove($user);
        $this->objectManager->flush();

        $output->writeln('User successfully deleted.');
    }
}
