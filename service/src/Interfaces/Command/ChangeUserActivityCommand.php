<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Doctrine\Common\Persistence\ObjectManager;
use Fxtm\CopyTrading\Interfaces\Controller\ValidationException;
use Fxtm\CopyTrading\Interfaces\Repository\UserRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChangeUserActivityCommand extends Command
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
        $this->setName('app:user:activity')
            ->setAliases(['a:u:a'])
            ->setDescription('Change activity of user.')
            ->setHelp('Change activity of user tu true (1) or false (0).')
            ->addArgument('username', InputArgument::REQUIRED, 'User name')
            ->addArgument('activity', InputArgument::REQUIRED, 'Activity: 1 or 0');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');
        $activity = $input->getArgument('activity');

        if ($activity != 1 && $activity != 0) {
            throw new ValidationException('Invalid activity! It should be 1 or 0.');
        }

        $user = $this->userRepository->getByUsername($username);
        if (!$user) {
            throw new ValidationException('Invalid login or old password!');
        }

        if ($activity) {
            $user->activate();
        } else {
            $user->deactivate();
        }

        $this->objectManager->flush();

        $output->writeln('Password successfully changed.');
    }
}
