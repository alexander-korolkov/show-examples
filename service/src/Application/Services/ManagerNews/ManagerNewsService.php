<?php

namespace Fxtm\CopyTrading\Application\Services\ManagerNews;

use Exception;
use Fxtm\CopyTrading\Application\Censorship\Censor;
use Fxtm\CopyTrading\Server\Generated\Model\ManagerNewsMultipleResponse;
use Psr\Log\LoggerInterface as Logger;
use Fxtm\CopyTrading\Application\Metrix\MetrixData;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\Security\Role;
use Fxtm\CopyTrading\Application\Services\LoggerTrait;
use Fxtm\CopyTrading\Application\Services\SecurityTrait;
use Fxtm\CopyTrading\Domain\Model\Leader\LeaderAccountRepository;
use Fxtm\CopyTrading\Domain\Model\News\News;
use Fxtm\CopyTrading\Domain\Model\News\NewsAlreadyApproved;
use Fxtm\CopyTrading\Domain\Model\News\NewsAlreadySubmitted;
use Fxtm\CopyTrading\Domain\Model\News\NewsRepository;
use Fxtm\CopyTrading\Domain\Model\News\NewsTextDeclinedByCensor;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNotRegistered;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Interfaces\Controller\ExitStatus;
use Fxtm\CopyTrading\Server\Generated\Model\ActionResult;
use Fxtm\CopyTrading\Server\Generated\Model\LightStrategyManager;
use Fxtm\CopyTrading\Server\Generated\Model\ManagerNews;
use Fxtm\CopyTrading\Server\Generated\Model\ManagerNewsResponse;
use Fxtm\CopyTrading\Server\Generated\Api\NewsApiInterface;
use Fxtm\CopyTrading\Server\Generated\Model\ManagerNewsContent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class ManagerNewsService implements NewsApiInterface
{
    use LoggerTrait;
    use SecurityTrait;

    /**
     * @var LeaderAccountRepository
     */
    private $leaderAccountRepository;

    /**
     * @var NewsRepository
     */
    private $newsRepository;

    /**
     * @var Censor
     */
    private $censor;

    /**
     * @var NotificationGateway
     */
    private $notificationGateway;

    /**
     * ManagerNewsService constructor.
     * @param LeaderAccountRepository $leaderAccountRepository
     * @param NewsRepository $newsRepository
     * @param Censor $censor
     * @param NotificationGateway $notificationGateway
     * @param Security $security
     * @param logger $logger
     */
    public function __construct(
        LeaderAccountRepository $leaderAccountRepository,
        NewsRepository $newsRepository,
        Censor $censor,
        NotificationGateway $notificationGateway,
        Security $security,
        Logger $logger
    ) {
        $this->leaderAccountRepository = $leaderAccountRepository;
        $this->newsRepository = $newsRepository;
        $this->censor = $censor;
        $this->notificationGateway = $notificationGateway;
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
    public function setjwt($value)
    {
    }

    /**
     * Operation leadersAccountNumberNewsPost
     *
     * Create manager news
     *
     * @param  string $accountNumber trade account&#39;s login (required)
     * @param  ManagerNewsContent $managerNewsContent (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ManagerNewsResponse
     *
     * @throws Exception
     */
    public function leadersAccountNumberNewsPost($accountNumber, ManagerNewsContent $managerNewsContent, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $newsId = $this->submitNews(
                new AccountNumber($accountNumber),
                $managerNewsContent->getTitle(),
                $managerNewsContent->getText()
            );

            $news = $this->newsRepository->getAsArray($newsId);
            $news['content'] = new ManagerNewsContent($news);
            $news['author'] = new LightStrategyManager($news);

            return new ManagerNewsResponse([
                'status' => ExitStatus::SUCCESS,
                'result' => new ManagerNews($news),
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
     * @param AccountNumber $accNo
     * @param $title
     * @param $text
     * @return string
     */
    private function submitNews(AccountNumber $accNo, $title, $text)
    {
        $this->ensureTextPassedCensorship($title);
        $this->ensureTextPassedCensorship($text);

        if (!$this->leaderAccountRepository->getLightAccount($accNo)) {
            throw new AccountNotRegistered($accNo->value());
        }

        if (!empty($news = $this->newsRepository->findOneUnderReview($accNo))) {
            throw new NewsAlreadySubmitted($news->id());
        }

        $news = new News($accNo, $title, $text);
        $this->newsRepository->store($news);

        return $news->id();
    }

    /**
     * @param $text
     */
    private function ensureTextPassedCensorship($text)
    {
        if (!$this->censor->pass($text)) {
            throw new NewsTextDeclinedByCensor($text);
        }
    }

    /**
     * Operation leadersNewsIdApprovePost
     *
     * Approve manager news
     *
     * @param  float $id id of concrete news (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ActionResult
     *
     * @throws Exception
     */
    public function leadersNewsIdApprovePost($id, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $news = $this->newsRepository->find($id);
            if (!$news) {
                $responseCode = 404;
                return null;
            }

            $news->approve();
            $this->newsRepository->store($news);

            $account = $this->leaderAccountRepository->getLightAccountOrFail($news->leaderAccountNumber());
            $this->notificationGateway->notifyClient(
                $account->ownerId(),
                $account->broker(),
                NotificationGateway::NEWS_APPROVED,
                [
                    'accNo' => $account->number()->value(),
                    'accName' => $account->name(),
                    'urlAccName' => $account->urlName(),
                    'newsTitle' => $news->title(),
                    'newsText' => $news->text()
                ]
            );

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
     * Operation leadersNewsIdGet
     *
     * Get concrete manager news by id
     *
     * @param  float $id id of concrete news (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ManagerNews
     *
     * @throws Exception
     */
    public function leadersNewsIdGet($id, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $news = $this->newsRepository->getAsArray($id);
            if (!$news) {
                $responseCode = 404;
                return null;
            }

            $news['content'] = new ManagerNewsContent($news);
            $news['author'] = new LightStrategyManager($news);

            return new ManagerNews($news);
        } catch (AccessDeniedException $e) {
            $responseCode = 403;
            return null;
        } catch (Exception $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Operation leadersNewsIdPatch
     *
     * Update manager news
     *
     * @param  float $id id of concrete news (required)
     * @param  ManagerNewsContent $managerNewsContent (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ManagerNewsResponse
     *
     * @throws Exception
     */
    public function leadersNewsIdPatch($id, ManagerNewsContent $managerNewsContent, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $news = $this->newsRepository->find($id);
            if (!$news) {
                $responseCode = 404;
                return null;
            }
            if ($news->isApproved()) {
                throw new NewsAlreadyApproved($id);
            }

            $this->updateNews($news, $managerNewsContent->getTitle(), $managerNewsContent->getText());

            $news = $this->newsRepository->getAsArray($id);
            $news['content'] = new ManagerNewsContent($news);
            $news['author'] = new LightStrategyManager($news);

            return new ManagerNewsResponse([
                'status' => ExitStatus::SUCCESS,
                'result' => new ManagerNews($news),
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
     * @param News $news
     * @param string $title
     * @param string $text
     */
    private function updateNews(News $news, $title, $text)
    {
        $this->ensureTextPassedCensorship($title);
        $this->ensureTextPassedCensorship($text);

        $news->setTitle($title);
        $news->setText($text);
        $news->review();

        $this->newsRepository->store($news);
    }

    /**
     * Operation leadersNewsIdRejectPost
     *
     * Reject manager news
     *
     * @param  float $id id of concrete news (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ActionResult
     *
     * @throws Exception
     */
    public function leadersNewsIdRejectPost($id, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::ADMIN, Role::CLIENT]);

            $news = $this->newsRepository->find($id);
            if (!$news) {
                $responseCode = 404;
                return null;
            }

            $news->reject();
            $this->newsRepository->store($news);

            $account = $this->leaderAccountRepository->getLightAccountOrFail($news->leaderAccountNumber());
            $this->notificationGateway->notifyClient(
                $account->ownerId(),
                $account->broker(),
                NotificationGateway::NEWS_REJECTED,
                [
                    'accNo' => $account->number()->value(),
                    'accName' => $account->name(),
                    'urlAccName' => $account->urlName(),
                    'newsTitle' => $news->title(),
                    'newsText' => $news->text()
                ]
            );

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
     * Operation newsGet
     *
     * Get all approved strategy managers news
     *
     * @param  string $accountNumber  Returns news of concrete strategy managers (optional)
     * @param  string $clientId  Returns news of strategy managers in which the client invested (optional)
     * @param  bool $approvedOnly  if true, endpoint will return only news in status approved (optional)
     * @param  string $rankType
     * @param  bool $isPublic  show news only from public managers (optional)
     * @param  int $limit   (optional)
     * @param  int $offset   (optional)
     * @param  integer $responseCode     The HTTP response code to return
     * @param  array   $responseHeaders  Additional HTTP headers to return with the response ()
     *
     * @return ManagerNewsMultipleResponse
     *
     * @throws Exception
     */
    public function newsGet(
        $accountNumber = null,
        $clientId = null,
        $approvedOnly = null,
        $rankType = null,
        $isPublic = null,
        $limit = null,
        $offset = null,
        &$responseCode,
        array &$responseHeaders
    ) {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $news = $this->newsRepository->getAll(
                $accountNumber,
                $clientId,
                $approvedOnly,
                $rankType,
                $isPublic,
                $limit,
                $offset
            );

            $total = $this->newsRepository->count(
                $accountNumber,
                $clientId,
                $approvedOnly,
                $rankType,
                $isPublic
            );

            return new ManagerNewsMultipleResponse([
                'totalItems' => $total,
                'items' => array_map(function (array $newsItem) {
                    $newsItem['content'] = new ManagerNewsContent($newsItem);
                    $newsItem['author'] = new LightStrategyManager($newsItem);

                    return new ManagerNews($newsItem);
                }, $news),
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
        return 'WEB[NEWS]';
    }
}
