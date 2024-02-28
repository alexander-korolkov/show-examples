<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Questionnaire;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Domain\Common\Objects;
use Fxtm\CopyTrading\Domain\Model\Questionnaire\Questionnaire;
use Fxtm\CopyTrading\Domain\Model\Questionnaire\QuestionnaireRepository;
use PDO;

class MySqlQuestionnaireRepository implements QuestionnaireRepository
{
    protected $dbConn = null;

    public function __construct(Connection $dbConn)
    {
        $this->dbConn = $dbConn;
    }

    public function store(Questionnaire $questionnaire)
    {
        $data = $questionnaire->toArray();
        $questions = $data["questions"];
        unset($data["questions"]);

        $stmt1 = $this->dbConn->prepare("
            INSERT INTO `questionnaire` (
                `id`,
                `status`,
                `created_at`,
                `published_at`
            ) VALUES (
                :id,
                :status,
                :created_at,
                :published_at
            ) ON DUPLICATE KEY UPDATE
                `status`       = VALUES(`status`),
                `published_at` = VALUES(`published_at`)
        ");
        $stmt1->execute($data);
        $questionnaireId = $this->dbConn->lastInsertId();

        array_walk($questions, function ($question) use ($questionnaireId) {
            $choices = $question["choices"];
            unset($question["choices"]);

            static $stmt = null;
            $stmt = $this->dbConn->prepare("
                INSERT INTO `questionnaire_questions` (
                    `id`,
                    `questionnaire_id`,
                    `no`,
                    `parent_no`,
                    `text`
                ) VALUES (
                    :id,
                    :questionnaire_id,
                    :no,
                    :parent_no,
                    :text
                ) ON DUPLICATE KEY UPDATE `text` = VALUES(`text`)
            ");
            $stmt->execute(["questionnaire_id" => $questionnaireId] + $question);
            $questionId = $this->dbConn->lastInsertId();

            array_walk($choices, function ($choice) use ($questionId) {
                static $stmt = null;
                $stmt = $this->dbConn->prepare("
                    INSERT INTO `questionnaire_questions_choices` (
                        `question_id`,
                        `no`,
                        `text`,
                        `points`
                    ) VALUES (
                        :question_id,
                        :no,
                        :text,
                        :points
                    ) ON DUPLICATE KEY UPDATE
                        `text`   = VALUES(`text`),
                        `points` = VALUES(`points`)
                ");
                $stmt->execute(["question_id" => $questionId] + $choice);
            });
        });
    }

    public function find($id)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM questionnaire WHERE id = ?");
        $stmt->execute([$id]);
        if (!empty($questionnaire = $stmt->fetch(PDO::FETCH_ASSOC))) {
            return $this->hydrate($questionnaire);
        }
    }

    public function findLatestPublished()
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM questionnaire WHERE status = 1 ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        if (!empty($questionnaire = $stmt->fetch(PDO::FETCH_ASSOC))) {
            return $this->hydrate($questionnaire);
        }
    }

    private function hydrate(array $questionnaire)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM questionnaire_questions WHERE questionnaire_id = ?");
        $stmt->execute([$questionnaire["id"]]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        array_walk($questions, function (&$question) {
            static $stmt = null;
            $stmt = $this->dbConn->prepare("SELECT * FROM questionnaire_questions_choices WHERE question_id = ?");
            $stmt->execute([$question["id"]]);
            $question["choices"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
        $questionnaire["questions"] = $questions;

        $instance = Objects::newInstance(Questionnaire::CLASS);
        $instance->fromArray($questionnaire);
        return $instance;
    }
}
