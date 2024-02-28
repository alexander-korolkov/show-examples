<?php

namespace Fxtm\CopyTrading\Domain\Model\Client;

use Fxtm\CopyTrading\Domain\Common\AbstractEntity;
use Fxtm\CopyTrading\Domain\Model\Company\Company;

class Client extends AbstractEntity
{
    private $questAttemptId = null;

    /**
     * @var Company
     */
    private $company;

    /**
     * @var int
     */
    private $statusId;

    /**
     * @var bool
     */
    private $isProfessional;

    /**
     * @var int
     */
    private $appropriatenessLeverage;

    /**
     * @var bool
     */
    private $isLockedSourceWealth;

    public function __construct(ClientId $id)
    {
        parent::__construct($id);
    }

    public function id()
    {
        return $this->identity;
    }

    public function setSuccessfullQuestionnaireAttemptId($questAttemptId)
    {
        $this->questAttemptId = $questAttemptId;
    }

    public function getSuccessfullQuestionnaireAttemptId()
    {
        return $this->questAttemptId;
    }

    public function toArray()
    {
        return [
            "id" => $this->identity->value(),
            "quest_attempt_id" => $this->questAttemptId
        ];
    }

    public function fromArray(array $array)
    {
        $this->identity       = new ClientId($array["id"]);
        $this->questAttemptId = $array["quest_attempt_id"];
    }

    public function __toString()
    {
        return print_r($this->toArray(), true);
    }

    /**
     * @param int $companyId
     */
    public function setCompany($companyId)
    {
        $this->company = new Company($companyId);
    }

    /**
     * @return Company
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * @param int $id
     */
    public function setStatusId($id)
    {
        $this->statusId = $id;
    }

    /**
     * @return int
     */
    public function getStatusId()
    {
        return $this->statusId;
    }

    /**
     * @var array
     */
    private $params = [];

    /**
     * @param array $params
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }

    /**
     * @param string $name
     * @return string
     */
    public function getParam($name)
    {
        return $this->params[$name] ?? null;
    }

    /**
     * @var array
     */
    private $leverageList = [];

    public function setLeverageList($leverageList)
    {
        $this->leverageList = $leverageList;
    }

    public function getMaxAllowedLeverageForAccountType($accType)
    {
        return isset($this->leverageList[$accType]) ? max($this->leverageList[$accType]) : $this->getAppropriatenessLeverage();
    }

    public function setIsProfessional($isProfessional)
    {
        $this->isProfessional = $isProfessional;
    }

    /**
     * @return bool
     */
    public function isProfessional(): bool
    {
        return $this->isProfessional;
    }

    public function setAppropriatenessLeverage($appropriatenessLeverage)
    {
        $this->appropriatenessLeverage = $appropriatenessLeverage;
    }

    /**
     * @return int
     */
    public function getAppropriatenessLeverage()
    {
        return $this->appropriatenessLeverage;
    }

    public function setIsLockedSourceWealth($isLockedSourceWealth)
    {
        $this->isLockedSourceWealth = $isLockedSourceWealth;
    }

    public function isLockedSourceWealth()
    {
        return $this->isLockedSourceWealth;
    }
}
