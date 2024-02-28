<?php

namespace Fxtm\CopyTrading\Interfaces\Command;

use Doctrine\Common\Persistence\ObjectManager;
use Fxtm\CopyTrading\Interfaces\Controller\ValidationException;
use Fxtm\CopyTrading\Interfaces\Repository\UserRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ChangeUserPasswordCommand extends Command
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
     * @var UserRepository
     */
    private $userRepository;

    /**
     * CreateUserCommand constructor.
     * @param ObjectManager $objectManager
     * @param UserPasswordEncoderInterface $encoder
     * @param UserRepository $userRepository
     */
    public function __construct(
        ObjectManager $objectManager,
        UserPasswordEncoderInterface $encoder,
        UserRepository $userRepository
    ) {
        $this->objectManager = $objectManager;
        $this->encoder = $encoder;
        $this->userRepository = $userRepository;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('app:user:change-password')
            ->setAliases(['a:u:cp'])
            ->setDescription('Change password of user.')
            ->setHelp('Command for changing password of user.')
            ->addArgument('username', InputArgument::REQUIRED, 'User name')
            ->addArgument('old_password', InputArgument::REQUIRED, 'Old user\'s password')
            ->addArgument('new_password', InputArgument::REQUIRED, 'New user\'s password');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');
        $oldPassword = $input->getArgument('old_password');
        $newPassword = $input->getArgument('new_password');

        $user = $this->userRepository->getByCredentials($username, $oldPassword);
        if (!$user) {
            throw new ValidationException('Invalid login or old password!');
        }

        $user->fill($user->getUsername(), $this->encoder->encodePassword($user, $newPassword), $user->getRole());
        $this->objectManager->flush();

        $output->writeln('Password successfully changed.');
    }
}
