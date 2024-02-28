<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\QuestionnaireAttempt;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Domain\Common\Objects;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\QuestionnaireAttempt\QuestionnaireAttempt;
use Fxtm\CopyTrading\Domain\Model\QuestionnaireAttempt\QuestionnaireAttemptRepository;
use PDO;

class MySqlQuestionnaireAttemptRepository implements QuestionnaireAttemptRepository
{
    protected $dbConn = null;

    public function __construct(Connection $dbConn)
    {
        $this->dbConn = $dbConn;
    }

    public function store(QuestionnaireAttempt $questAttempt)
    {
        $data = $questAttempt->toArray();
        $answers = $data["answers"];
        unset($data["answers"]);

        $stmt = $this->dbConn->prepare("
            INSERT INTO questionnaire_attempts (
                `id`,
                `client_id`,
                `questionnaire_id`,
                `submitted_at`,
                `points`,
                `result`
            ) VALUES (
                :id,
                :client_id,
                :questionnaire_id,
                :submitted_at,
                :points,
                :result
            )
        ");
        $stmt->execute($data);
        $questAttemptId = $this->dbConn->lastInsertId();
        $questAttempt->fromArray(array_merge($questAttempt->toArray(), ["id" => $questAttemptId]));

        array_walk($answers, function ($choiceNo, $questionNo) use ($questAttemptId) {
            static $stmt = null;
            $stmt = $this->dbConn->prepare("
                INSERT INTO `questionnaire_attempts_answers` (
                    `attempt_id`,
                    `question_no`,
                    `choice_no`
                ) VALUES (
                    :attempt_id,
                    :question_no,
                    :choice_no
                )
            ");
            $stmt->execute([
                "attempt_id"  => $questAttemptId,
                "question_no" => $questionNo,
                "choice_no"   => $choiceNo
            ]);
        });
    }

    public function find($id)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM questionnaire_attempts WHERE id = ?");
        $stmt->execute([$id]);
        if (!empty($questAttempt = $stmt->fetch(PDO::FETCH_ASSOC))) {
            return $this->hydrate($questAttempt);
        }
    }

    public function findByClientId(ClientId $clientId)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM questionnaire_attempts WHERE client_id = ? ORDER BY id DESC");
        $stmt->execute([$clientId->value()]);
        if (!empty($questAttempt = $stmt->fetch(PDO::FETCH_ASSOC))) {
            return $this->hydrate($questAttempt);
        }
    }

    private function hydrate(array $questAttempt)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM questionnaire_attempts_answers WHERE attempt_id = ?");
        $stmt->execute([$questAttempt["id"]]);
        array_walk($stmt->fetchAll(PDO::FETCH_ASSOC), function ($answer) use (&$questAttempt) {
            $questAttempt["answers"][$answer["question_no"]] = $answer["choice_no"];
        });
        return Objects::newInstance(QuestionnaireAttempt::CLASS, $questAttempt);
    }

    /**
     * {@inheritDoc}
     */
    public function findLastAttempt(string $clientId)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM questionnaire_attempts WHERE client_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$clientId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
