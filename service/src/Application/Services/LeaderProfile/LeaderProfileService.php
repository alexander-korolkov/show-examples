<?php

namespace Fxtm\CopyTrading\Application\Services\LeaderProfile;

use Exception;
use Fxtm\CopyTrading\Application\Censorship\Censor;
use Psr\Log\LoggerInterface as Logger;
use Fxtm\CopyTrading\Application\FileStorageGateway;
use Fxtm\CopyTrading\Application\Metrix\MetrixData;
use Fxtm\CopyTrading\Application\Security\Role;
use Fxtm\CopyTrading\Application\Services\LoggerTrait;
use Fxtm\CopyTrading\Application\Services\SecurityTrait;
use Fxtm\CopyTrading\Application\UniqueNameChecker\UniqueNameChecker;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\LeaderProfile\LeaderProfileRepository;
use Fxtm\CopyTrading\Domain\Model\LeaderProfile\NicknameAlreadyTaken;
use Fxtm\CopyTrading\Domain\Model\LeaderProfile\NicknameDeclinedByCensor;
use Fxtm\CopyTrading\Interfaces\Controller\ExitStatus;
use Fxtm\CopyTrading\Server\Generated\Api\LeaderProfileApiInterface;
use Fxtm\CopyTrading\Server\Generated\Model\ActionResult;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeAvatarRequest;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeNicknameRequest;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeShowCountryRequest;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeShowNameRequest;
use Fxtm\CopyTrading\Server\Generated\Model\ChangeUseNicknameRequest;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderProfile;
use Fxtm\CopyTrading\Server\Generated\Model\LeaderProfileResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class LeaderProfileService implements LeaderProfileApiInterface
{

    use LoggerTrait, SecurityTrait;

    /**
     * @var LeaderProfileRepository
     */
    private $leaderProfileRepository;

    /**
     * @var FileStorageGateway
     */
    private $fileStorage;

    /**
     * @var Censor
     */
    private $censor;

    /**
     * @var UniqueNameChecker
     */
    private $uniqueNameChecker;

    /**
     * LeaderProfileService constructor.
     * @param LeaderProfileRepository $leaderProfileRepository
     * @param FileStorageGateway $fileStorage
     * @param Censor $censor
     * @param UniqueNameChecker $uniqueNameChecker
     * @param Security $security
     * @param Logger $logger
     */
    public function __construct(
        LeaderProfileRepository $leaderProfileRepository,
        FileStorageGateway $fileStorage,
        Censor $censor,
        UniqueNameChecker $uniqueNameChecker,
        Security $security,
        Logger $logger
    ) {
        $this->leaderProfileRepository = $leaderProfileRepository;
        $this->fileStorage = $fileStorage;
        $this->censor = $censor;
        $this->uniqueNameChecker = $uniqueNameChecker;
        $this->setSecurityHandler($security);
        $this->setLogger($logger);
    }


    /**
     * Sets authentication method jwt
     *
     * @param string $value Value of the jwt authentication method.
     *
     * @return void
     */
    public function setjwt($value) {}

    /**
     * Operation leadersClientIdProfileAvatarDelete
     *
     * Remove manager's profile avatar
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ActionResult
     *
     * @throws Exception
     */
    public function leadersClientIdProfileAvatarDelete($clientId, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $profile = $this->leaderProfileRepository->find(new ClientId($clientId));
            if (!$profile || empty($profile->avatar())) {
                $responseCode = 404;
                return null;
            }

            $this->fileStorage->delete("profiles/{$clientId}_{$profile->avatar()}.jpeg");
            $profile->removeAvatar();
            $this->leaderProfileRepository->store($profile);

            return new ActionResult([
                'status' => ExitStatus::SUCCESS,
                'result' => true,
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersClientIdProfileAvatarPost
     *
     * Upload manager's profile avatar
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  ChangeAvatarRequest $changeAvatarRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ActionResult
     *
     * @throws Exception
     */
    public function leadersClientIdProfileAvatarPost($clientId, ChangeAvatarRequest $changeAvatarRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $fileName = $changeAvatarRequest->getName();

            $profile = $this->leaderProfileRepository->findOrNew(new ClientId($clientId));
            if (!empty($profile->avatar())) {
                $this->fileStorage->delete("profiles/{$clientId}_{$profile->avatar()}.jpeg");
            }

            $avatar = base64_decode($changeAvatarRequest->getAvatar());
            $this->fileStorage->write("profiles/{$fileName}", $avatar);

            $ts = substr($fileName, strpos($fileName, '_') + 1, -5);
            $profile->changeAvatar($ts);
            $this->leaderProfileRepository->store($profile);

            return new ActionResult([
                'status' => ExitStatus::SUCCESS,
                'result' => true,
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersClientIdProfileGet
     *
     * Get manager's profile
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return LeaderProfileResponse
     *
     * @throws Exception
     */
    public function leadersClientIdProfileGet($clientId, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $profile = $this->leaderProfileRepository->findOrNew(new ClientId($clientId));

            return new LeaderProfileResponse([
                'status' => ExitStatus::SUCCESS,
                'result' => new LeaderProfile([
                    'nickname' => $profile->nickname(),
                    'leaderId' => $profile->leaderId()->value(),
                    'avatar' => $profile->avatar(),
                    'useNickname' => $profile->getUseNickname(),
                    'showName' => $profile->getShowName(),
                    'showCountry' => $profile->getShowCountry(),
                ]),
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersClientIdProfileNicknamePatch
     *
     * Change manager's name
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  ChangeNicknameRequest $changeNicknameRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ActionResult
     *
     * @throws Exception
     */
    public function leadersClientIdProfileNicknamePatch($clientId, ChangeNicknameRequest $changeNicknameRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $this->ensurePassedCensorship($changeNicknameRequest->getNickname());
            $this->ensureUniqueNickname($clientId, $changeNicknameRequest->getNickname());

            $profile = $this->leaderProfileRepository->findOrNew(new ClientId($clientId));
            $profile->setNickname($changeNicknameRequest->getNickname());

            $this->leaderProfileRepository->store($profile);

            return new ActionResult([
                'status' => ExitStatus::SUCCESS,
                'result' => true,
            ]);
        } catch (NicknameDeclinedByCensor $e) {
            return new ActionResult([
                'status' => ExitStatus::LEADER_NICKNAME_DECLINED_BY_CENSOR,
                'result' => false,
            ]);
        } catch (NicknameAlreadyTaken $e) {
            return new ActionResult([
                'status' => ExitStatus::LEADER_NICKNAME_ALREADY_TAKEN,
                'result' => false,
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * @param string $nickname
     * @throws NicknameDeclinedByCensor
     */
    private function ensurePassedCensorship($nickname)
    {
        if (!$this->censor->pass($nickname)) {
            throw new NicknameDeclinedByCensor($nickname);
        }
    }

    /**
     * @param string $clientId
     * @param string $nickname
     * @throws NicknameAlreadyTaken
     */
    private function ensureUniqueNickname($clientId, $nickname)
    {
        if (!$this->uniqueNameChecker->isUniqueFullName($clientId, $nickname)) {
            throw new NicknameAlreadyTaken($nickname);
        }
    }

    /**
     * Operation leadersClientIdProfileShowCountryPatch
     *
     * Change manager's show_country method
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  ChangeShowCountryRequest $changeShowCountryRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ActionResult
     *
     * @throws Exception
     */
    public function leadersClientIdProfileShowCountryPatch($clientId, ChangeShowCountryRequest $changeShowCountryRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $profile = $this->leaderProfileRepository->findOrNew(new ClientId($clientId));
            $profile->showCountry($changeShowCountryRequest->isShowCountry());
            $this->leaderProfileRepository->store($profile);

            return new ActionResult([
                'status' => ExitStatus::SUCCESS,
                'result' => true,
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersClientIdProfileShowNamePatch
     *
     * Change manager's show_name setting
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  ChangeShowNameRequest $changeShowNameRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ActionResult
     *
     * @throws Exception
     */
    public function leadersClientIdProfileShowNamePatch($clientId, ChangeShowNameRequest $changeShowNameRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $profile = $this->leaderProfileRepository->findOrNew(new ClientId($clientId));
            $profile->showName($changeShowNameRequest->isShowName());
            $this->leaderProfileRepository->store($profile);

            return new ActionResult([
                'status' => ExitStatus::SUCCESS,
                'result' => true,
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersClientIdProfileUseNicknamePatch
     *
     * Change manager's use_nickname setting
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  ChangeUseNicknameRequest $changeUseNicknameRequest (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ActionResult
     *
     * @throws Exception
     */
    public function leadersClientIdProfileUseNicknamePatch($clientId, ChangeUseNicknameRequest $changeUseNicknameRequest, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $profile = $this->leaderProfileRepository->findOrNew(new ClientId($clientId));
            $profile->useNickname($changeUseNicknameRequest->isUseNickname());
            $this->leaderProfileRepository->store($profile);

            return new ActionResult([
                'status' => ExitStatus::SUCCESS,
                'result' => true,
            ]);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * @return string
     */
    public function getWorkerName()
    {
        return 'WEB[PROFILES]';
    }
}
