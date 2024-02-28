<?php

namespace Fxtm\CopyTrading\Interfaces\Gateway\Client;

use Exception;
use Fxtm\CopyTrading\Application\ClientGateway;
use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Entity\MetaData\DataSourceFactory;
use Fxtm\CopyTrading\Domain\Model\Client\ClientId;
use Fxtm\CopyTrading\Domain\Model\Client\Client;
use PDO;

class ClientGatewayRESTImpl implements ClientGateway {

    public const CLIENT_STATUS_INACTIVE = 16;

    /**
     * @var array
     */
    private $cmsConfig;

    /**
     * @var DataSourceFactory
     */
    private $factory;

    public function __construct(DataSourceFactory $factory, array $cmsConfig)
    {
        $this->factory = $factory;
        $this->cmsConfig = $cmsConfig;
    }

    public function fetchClientByClientId(ClientId $clientId, $broker): Client
    {

        $response = $this->request(['pk' => $clientId->value()], $broker, $this->cmsConfig[$broker]['url']['client'], 'GET');

        $client = new Client($clientId);
        $client->setParams($response['params']);
        $client->setCompany($response['company_id']);
        $client->setStatusId($response['status_id']);
        $client->setLeverageList($response['leverages_list']);
        $client->setIsProfessional($response['is_professional']);
        $client->setAppropriatenessLeverage($response['appropriateness_leverage']);
        $client->setIsLockedSourceWealth($response['is_locked_source_wealth']);

        return $client;
    }

    public function getAppropriatenessTestAnswers(ClientId $clientId, $broker): array
    {
        $answers = [];
        $client = $this->fetchClientByClientId($clientId, $broker);

        //birth date
        if (empty($birthDate = $client->getParam('birth_date'))) {
            $answers[1] = 0;
        } else {
            $age = date('Y') - DateTime::createFromFormat('Y-m-d', $birthDate)->format('Y');
            if ($age >= 18 && $age < 50) { $answers[1] = 1; } else
                if ($age >= 50 && $age < 70) { $answers[1] = 2; } else
                    if ($age >= 70             ) { $answers[1] = 3; }
        }

        // profession
        $map2 = [
            6 => 16,
            7 => 20,
            8 => 20,
            9 => 17,
            10 => 18,
            11 => 14,
            12 => 14,
            13 => 19,
            14 => 1,
            15 => 3,
            16 => 4,
            17 => 5,
            18 => 6,
            19 => 7,
            20 => 8,
            21 => 9,
            22 => 10,
            23 => 11,
            24 => 12,
            25 => 13,
            26 => 14,
            27 => 15,
            28 => 20,
            29 => 2,
        ];
        $answers[2] = empty($profession = $client->getParam('education_id')) || !in_array($profession, array_keys($map2)) ? 0 : $map2[$profession];

        // education level
        $answers[3] = empty($eduLevel = $client->getParam('education_level')) ? 0 : intval($eduLevel);

        // annual income
        $map4 = [
            4 => 1,
            5 => 2,
            2 => 3,
            3 => 4,
        ];
        $answers[4] = empty($income = $client->getParam('financial_income_id')) || !isset($map4[$income])
            ? 0
            : intval($map4[$income]);

        // net worth
        $answers[5] = empty($netWorth = $client->getParam('net_worth_id')) ? 0 : intval($netWorth);

        // funds source
        $answers[6] = empty($fundsSource = $client->getParam('individual_funds_source_id')) ? 0 : intval($fundsSource);

        // turnover
        $map7 = [
            1 => 1,
            2 => 1,
            3 => 1,
            5 => 1,
            6 => 2,
            8 => 3,
        ];
        $answers[7] = empty($turnover = $client->getParam('anticipated_turnover')) || !in_array($turnover, array_keys($map7)) ? 0 : $map7[$turnover];

        $conn = $this->factory->getMyConnection($broker);

        // FSA questions ("Appropriateness" Test)
        $testAttempt = $this->getLatestTestAttempt($clientId->value(), $broker);

        $stmt = $conn->prepare("
            SELECT qd.question_id, GROUP_CONCAT(qd.answer_id SEPARATOR ',')
            FROM questionnaire_data qd
            JOIN questionnaire_client_answers qca ON qca.id = qd.question_client_answer
            WHERE qca.id = ?
            GROUP BY qd.question_id
        ");
        $stmt->execute([$testAttempt["id"]]);

        $atAnswers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        if ($testAttempt["version"] == 1) {
            // experience
            $answers[8] = empty($atAnswers[4]) ? 0 : (in_array(52, explode(',', $atAnswers[4])) ? 2 : 1);

            // experience duration 1
            $map9 = [
                43 => 1,
                44 => 2, // 2-3 -> 1-3
                45 => 2, // 1-2 -> 1-3
                46 => 3,
                47 => 4,
            ];
            $answers[9] = empty($atAnswers[3]) || !in_array($atAnswers[3], array_keys($map9)) ? 0 : $map9[$atAnswers[3]];

            // experience duration 2
            $map10 = [
                43 => 1,
                44 => 2, // 2-3 -> 0-3
                45 => 2, // 1-2 -> 0-3
                46 => 2, // 0-1 -> 0-3
                47 => 3,
            ];
            $answers[10] = empty($atAnswers[3]) || !in_array($atAnswers[3], array_keys($map10)) ? 0 : $map10[$atAnswers[3]];

            // average trades per quarter
            $map11 = [
                53 => 1,
                54 => 2,
                55 => 3,
                56 => 4,
            ];
            $answers[11] = empty($atAnswers[5]) || !in_array($atAnswers[5], array_keys($map11)) ? 0 : $map11[$atAnswers[5]];
        } else if ($testAttempt["version"] == 2) {
            // experience
            $answers[8] = empty($atAnswers[16]) ? 0 : (in_array(89, explode(',', $atAnswers[16])) ? 2 : 1);

            // experience duration 1
            $map9 = [
                82 => 1,
                83 => 2,
                84 => 3,
                85 => 4,
            ];
            $answers[9] = empty($atAnswers[15]) || !in_array($atAnswers[15], array_keys($map9)) ? 0 : $map9[$atAnswers[15]];

            // experience duration 2
            $map10 = [
                82 => 1,
                83 => 2,
                84 => 2,
                85 => 3,
            ];
            $answers[10] = empty($atAnswers[15]) || !in_array($atAnswers[15], array_keys($map10)) ? 0 : $map10[$atAnswers[15]];

            // average trades per quarter
            $map11 = [
                90 => 1,
                91 => 2,
                92 => 3,
                93 => 4,
            ];
            $answers[11] = empty($atAnswers[17]) || !in_array($atAnswers[17], array_keys($map11)) ? 0 : $map11[$atAnswers[17]];
        } else /* if ($testAttempt["version"] == 3) */ {
            // experience
            $answers[8] = empty($atAnswers[27]) ? 0 : (in_array(123, explode(',', $atAnswers[27])) ? 2 : 1);

            // experience duration 1
            $map9 = [
                124 => 1,
                125 => 2,
                126 => 3,
                127 => 4,
            ];
            $answers[9] = empty($atAnswers[28]) || !in_array($atAnswers[28], array_keys($map9)) ? 0 : $map9[$atAnswers[28]];

            // Frequency
            $map10 = [
                128 => 0,
                129 => 1,
                130 => 2,
                131 => 3,
            ];
            $answers[10] = empty($atAnswers[29]) || !in_array($atAnswers[29], array_keys($map10)) ? 0 : $map10[$atAnswers[29]];

            // average trades per quarter
            $map11 = [
                141 => 1,
                142 => 2,
                175 => 3,
                144 => 4,
            ];
            $answers[11] = empty($atAnswers[32]) || !in_array($atAnswers[32], array_keys($map11)) ? 0 : $map11[$atAnswers[32]];
        }

        return $answers;
    }

    public function getPointsKnowledgeAppropriatenessTest(ClientId $clientId, $broker): float
    {
        $testAttempt = $this->getLatestTestAttempt($clientId->value(), $broker);

        $conn = $this->factory->getMyConnection($broker);
        $stmt = $conn->prepare("
            SELECT SUM(qa.weight) AS sum_points FROM questionnaire_answer AS qa
              INNER JOIN questionnaire_data AS qcd ON qcd.answer_id = qa.id
              INNER JOIN questionnaire_client_answers AS qca ON qca.id = qcd.question_client_answer
              INNER JOIN questionnaire_question AS qq ON qq.id = qcd.question_id
            WHERE qq.type_id = qca.question_type_id
              AND qq.parent_id = (SELECT id FROM questionnaire_question WHERE alias = 'knowledge' AND type_id = qca.question_type_id)
              AND qca.id = ?
        ");
        $stmt->execute([$testAttempt["id"]]);

        return floatval($stmt->fetchColumn());
    }

    public function isUniqueFullname($name, $broker): bool
    {
        return intval(
                $this->factory
                    ->getMyConnection($broker)
                    ->executeQuery("SELECT count(*) FROM `client` WHERE CONCAT(`name`, ' ', `forename`) LIKE ?", [$name])
                    ->fetchColumn()
            ) == 0;
    }

    public function clientInInactiveStatus(ClientId $clientId, $broker)
    {
        return self::CLIENT_STATUS_INACTIVE != $this->fetchClientByClientId($clientId, $broker)->getStatusId();
    }

    /**
     * @param $clientId
     * @param $broker
     * @return mixed
     * @throws \Exception
     */
    private function getLatestTestAttempt($clientId, $broker)
    {
        $conn = $this->factory->getMyConnection($broker);
        $stmt = $conn->prepare("
                SELECT
                  id,
                  question_type_id AS version
                FROM questionnaire_client_answers
                WHERE client_id = ?
                ORDER BY ts DESC
                LIMIT 1
            ");
        $stmt->execute([$clientId]);

        $testAttempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($testAttempt)) {
            throw new \Exception("Appropriateness Test not taken");
        }

        return $testAttempt;
    }

    /**
     * @param array $requestData
     * @param string $broker
     * @param string $endpoint
     * @param string $method
     * @return array
     * @throws Exception|\GuzzleHttp\Exception\GuzzleException
     */
    private function request(array $requestData, string $broker, string $endpoint, string $method = 'POST'): array
    {

        $guzzle = new \GuzzleHttp\Client(['http_errors' => false]);

        if ($method == 'POST') {
            $dataType = 'json';
        } else {
            $dataType = 'query';
        }

        $response = $guzzle->request($method, $endpoint, [
            $dataType => $requestData,
            'curl' => [
                CURLOPT_SSLCERT => $this->cmsConfig[$broker]['cert'],
                CURLOPT_SSLKEY => $this->cmsConfig[$broker]['key'],
            ],
        ]);

        $bodyStr = $response->getBody()->getContents();

        $result = json_decode($bodyStr, true);

        // Do not throw exception if the response status is 500 but valid JSON received;
        // This is related to internal CMS issues
        if($response->getStatusCode() == 500 && $result != null && isset($result['params'])) {
            return $result;
        }

        if ($response->getStatusCode() != 200) {
            throw new Exception("request(): status not 200 ({$response->getStatusCode()}): {$bodyStr}");
        }

        if($result == null) {
            throw new Exception("request(): Invalid response ({$response->getStatusCode()})");
        }

        return $result;
    }

}