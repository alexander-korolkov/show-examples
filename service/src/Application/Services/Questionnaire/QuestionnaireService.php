<?php

namespace Fxtm\CopyTrading\Application\Services\Questionnaire;

use Exception;
use Fxtm\CopyTrading\Application\ClientGateway;
use Psr\Log\LoggerInterface as Logger;
use Fxtm\CopyTrading\Application\Metrix\MetrixData;
use Fxtm\CopyTrading\Application\NotificationGateway;
use Fxtm\CopyTrading\Application\Security\Role;
use Fxtm\CopyTrading\Application\Services\LoggerTrait;
use Fxtm\CopyTrading\Application\Services\SecurityTrait;
use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Model\Client\Client;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Client\ClientRepository;
use Fxtm\CopyTrading\Domain\Model\Questionnaire\QuestionnaireRepository;
use Fxtm\CopyTrading\Domain\Model\QuestionnaireAttempt\QuestionnaireAttempt;
use Fxtm\CopyTrading\Domain\Model\QuestionnaireAttempt\QuestionnaireAttemptRepository;
use Fxtm\CopyTrading\Interfaces\Controller\ExitStatus;
use Fxtm\CopyTrading\Server\Generated\Api\QuestionnaireApiInterface;
use Fxtm\CopyTrading\Server\Generated\Model\ActionResult;
use Fxtm\CopyTrading\Server\Generated\Model\LastQuestionnaireAttempt;
use Fxtm\CopyTrading\Server\Generated\Model\LastQuestionnaireAttemptResult;
use Fxtm\CopyTrading\Server\Generated\Model\Questionnaire;
use Fxtm\CopyTrading\Server\Generated\Model\QuestionnaireAttempt as QuestionnaireAttemptRequest;;
use Fxtm\CopyTrading\Server\Generated\Model\QuestionnaireAttemptAnswers;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class QuestionnaireService implements QuestionnaireApiInterface
{

    use LoggerTrait, SecurityTrait;

    /**
     * @var ClientRepository
     */
    private $clientRepository;

    /**
     * @var ClientGateway
     */
    private $clientGateway;

    /**
     * @var QuestionnaireRepository
     */
    private $questionnaireRepository;

    /**
     * @var SettingsRegistry
     */
    private $settingsRegistry;

    /**
     * @var QuestionnaireAttemptRepository
     */
    private $questionnaireAttemptRepository;

    /**
     * @var NotificationGateway
     */
    private $notificationGateway;

    /**
     * QuestionnaireService constructor.
     * @param ClientRepository $clientRepository
     * @param ClientGateway $clientGateway
     * @param QuestionnaireRepository $questionnaireRepository
     * @param QuestionnaireAttemptRepository $questionnaireAttemptRepository
     * @param SettingsRegistry $settingsRegistry
     * @param NotificationGateway $notificationGateway
     * @param Security $security
     * @param Logger $logger
     */
    public function __construct(
        ClientRepository $clientRepository,
        ClientGateway $clientGateway,
        QuestionnaireRepository $questionnaireRepository,
        QuestionnaireAttemptRepository $questionnaireAttemptRepository,
        SettingsRegistry $settingsRegistry,
        NotificationGateway $notificationGateway,
        Security $security,
        Logger $logger
    ) {
        $this->clientRepository = $clientRepository;
        $this->clientGateway = $clientGateway;
        $this->questionnaireRepository = $questionnaireRepository;
        $this->settingsRegistry = $settingsRegistry;
        $this->questionnaireAttemptRepository = $questionnaireAttemptRepository;
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
    public function setjwt($value) {}

    /**
     * Operation questionnairesClientIdGet
     *
     * Get questionnaire attempt id of client
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return Questionnaire
     *
     * @throws Exception
     */
    public function questionnairesClientIdGet($clientId, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $client = $this->getByClient(new ClientId($clientId));
            if (!$client) {
                $responseCode = 404;
                return null;
            }

            return new Questionnaire([
                'result' => $client->getSuccessfullQuestionnaireAttemptId(),
                'status' => ExitStatus::SUCCESS,
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
     * @param ClientId $clientId
     * @return Client
     */
    private function getByClient(ClientId $clientId)
    {
        return $this->clientRepository->find($clientId);
    }

    /**
     * Operation questionnairesClientIdSubmitPost
     *
     * Submit questionnaire attempt
     *
     * @param  string $clientId client&#39;s MyFXTM id (required)
     * @param  QuestionnaireAttemptRequest $questionnaireAttempt (required)
     * @param  integer $responseCode The HTTP response code to return
     * @param  array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return ActionResult
     *
     * @throws Exception
     */
    public function questionnairesClientIdSubmitPost($clientId, QuestionnaireAttemptRequest $questionnaireAttempt, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            return new ActionResult([
                'result' => $this->submitQuestionnaireAnswers(
                    new ClientId($clientId),
                    $questionnaireAttempt->getBroker(),
                    $questionnaireAttempt->getAnswers()
                ),
                'status' => ExitStatus::SUCCESS,
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
     * @param ClientId $clientId
     * @param string $broker
     * @param array $stAnswers
     * @return bool
     * @throws \Exception
     */
    private function submitQuestionnaireAnswers(ClientId $clientId, $broker, array $stAnswers)
    {
        $stAnswers = $this->convertAnswers($stAnswers);

        if (empty($client = $this->getByClient($clientId))) {
            $client = new Client($clientId);
            $this->clientRepository->store($client);
        }

        if (empty($stAnswers)) {
            throw new Exception("No answers submitted");
        }

        $atAnswers = $this->clientGateway->getAppropriatenessTestAnswers($clientId, $broker);
        $atPointsKnowledge = $this->clientGateway->getPointsKnowledgeAppropriatenessTest($clientId, $broker);

        $knowledgeRanges = [
            ['from' => 0, 'to' => 34, 'knowledgeAnswerId' => 0],
            ['from' => 35, 'to' => 40, 'knowledgeAnswerId' => 1],
            ['from' => 41, 'to' => 44, 'knowledgeAnswerId' => 2],
            ['from' => 45, 'to' => 51, 'knowledgeAnswerId' => 3],
        ];

        foreach ($knowledgeRanges as $range) {
            if($atPointsKnowledge >= $range['from'] && $atPointsKnowledge <= $range['to']) {
                $atAnswers[12] = $range['knowledgeAnswerId'];
                break;
            }
        }

        // Combine all answers in one array indexed from 1
        $allAnswers = array_combine(
            range(1, sizeof($atAnswers) + sizeof($stAnswers)),
            array_values(array_merge($atAnswers, $stAnswers))
        );

        if (empty($questionnaire = $this->questionnaireRepository->findLatestPublished())) {
            throw new Exception("No questionnaire published");
        }

        $allPoints = $questionnaire->assess($allAnswers);

        $result = $allPoints >= intval($this->settingsRegistry->get("follower.questionnaire_threshold"));

        $questAttempt = new QuestionnaireAttempt($clientId->value(), $questionnaire->id(), $allAnswers, $allPoints, $result);
        $this->questionnaireAttemptRepository->store($questAttempt);

        $client->setSuccessfullQuestionnaireAttemptId($result ? $questAttempt->id() : null);
        $this->clientRepository->store($client);

        $this->notificationGateway->notifyClient(
            $clientId,
            $broker,
            $result ? NotificationGateway::QUESTIONNAIRE_SUCCESS : NotificationGateway::QUESTIONNAIRE_FAIL
        );

        return $result;
    }

    /**
     * Converts answer objects to a simple key-value array
     *
     * @param array $answerObjects
     * @return array
     */
    private function convertAnswers(array $answerObjects) : array
    {
        $result = [];
        /** @var QuestionnaireAttemptAnswers $answerObject */
        foreach ($answerObjects as $answerObject) {
            $result[$answerObject->getQuestion()] = $answerObject->getAnswer();
        }

        return $result;
    }

    /**
     * Operation questionnairesLastAttemptClientIdGet
     *
     * Get last questionnaire attempt data of client
     *
     * @param string $clientId client&#39;s MyFXTM id (required)
     * @param integer $responseCode The HTTP response code to return
     * @param array $responseHeaders Additional HTTP headers to return with the response ()
     *
     * @return LastQuestionnaireAttempt
     *
     * @throws Exception
     */
    public function questionnairesLastAttemptClientIdGet($clientId, &$responseCode, array &$responseHeaders)
    {
        MetrixData::setWorker($this->getWorkerName());

        try {
            $this->assertRequesterRoles([Role::CLIENT]);

            $result = null;

            $attempt = $this->questionnaireAttemptRepository->findLastAttempt($clientId);

            return new LastQuestionnaireAttempt([
                'result' => $attempt
                    ? new LastQuestionnaireAttemptResult([
                        'id' => $attempt['id'],
                        'clientId' => $attempt['client_id'],
                        'questionnaireId' => $attempt['questionnaire_id'],
                        'submittedAt' => $attempt['submitted_at'],
                        'points' => $attempt['points'],
                        'result' => $attempt['result'],
                    ])
                    : null,
                'status' => ExitStatus::SUCCESS,
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
        return 'WEB[QUESTIONNAIRES]';
    }
}
