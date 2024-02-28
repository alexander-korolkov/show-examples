<?php

namespace Fxtm\CopyTrading\Interfaces\Persistence\Workflow;

use Doctrine\DBAL\Connection;
use Fxtm\CopyTrading\Application\Common\Workflow\AbstractWorkflow;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowFactory;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowRepository;
use Fxtm\CopyTrading\Application\Common\Workflow\WorkflowState;
use PDO;

class MySqlWorkflowRepository implements WorkflowRepository
{
    /**
     * @var WorkflowFactory
     */
    private $factory;

    /**
     * @var Connection
     */
    private $dbConn;

    public function __construct(Connection $dbConn, /*WorkflowFactory*/ $factory)
    {
        $this->dbConn = $dbConn;
        $this->factory = $factory;
    }

    public function store(AbstractWorkflow $workflow)
    {
        $this->dbConn->beginTransaction();

        try {
            $workflowStmt = $this->dbConn->prepare("
            INSERT INTO `workflows` (
                `id`,
                `parent_id`,
                `nesting_level`,
                `type`,
                `corr_id`,
                `state`,
                `tries`,
                `created_at`,
                `scheduled_at`,
                `started_at`,
                `finished_at`,
                `context`,
                `context_init`,
                `broker`
            ) VALUES (
                :id,
                :parent_id,
                :nesting_level,
                :type,
                :corr_id,
                :state,
                :tries,
                :created_at,
                :scheduled_at,
                :started_at,
                :finished_at,
                :context,
                :context_init,
                :broker
            ) ON DUPLICATE KEY UPDATE
                `corr_id`      = VALUES(`corr_id`),
                `state`        = VALUES(`state`),
                `tries`        = VALUES(`tries`),
                `scheduled_at` = VALUES(`scheduled_at`),
                `started_at`   = VALUES(`started_at`),
                `finished_at`  = VALUES(`finished_at`),
                `context`      = VALUES(`context`),
                `context_init` = VALUES(`context_init`),
                `broker`       = VALUES(`broker`)
        ");

            $params = $workflow->toArray();
            $params["corr_id"] = $workflow->getCorrelationId();
            $params["context"] = json_encode($params["context"]);
            $params["context_init"] = json_encode($params["context_init"]);
            $workflowStmt->execute($params);
            $workflow->fromArray(array_merge($workflow->toArray(), ["id" => $workflow->id() ? $workflow->id() : $this->dbConn->lastInsertId()]));

            $activities = $workflow->getActivities();
            $activityStmt = $this->dbConn->prepare("
            INSERT INTO `activities` (
                `id`,
                `workflow_id`,
                `name`,
                `state`,
                `tries`,
                `started_at`,
                `finished_at`,
                `context`
            ) VALUES (
                :id,
                :workflow_id,
                :name,
                :state,
                :tries,
                :started_at,
                :finished_at,
                :context
            ) ON DUPLICATE KEY UPDATE
                `state`        = VALUES(`state`),
                `tries`        = VALUES(`tries`),
                `started_at`   = VALUES(`started_at`),
                `finished_at`  = VALUES(`finished_at`),
                `context`      = VALUES(`context`)
        ");
            foreach ($activities as $activity) {
                $params = $activity->toArray();
                $params["workflow_id"] = $workflow->id();
                $params["context"] = json_encode($params["context"]);
                $activityStmt->execute($params);
                $activity->fromArray(array_merge($activity->toArray(), ["id" => $activity->id() ? $activity->id() : $this->dbConn->lastInsertId()]));
            }
            $this->dbConn->commit();
        } catch (\Throwable $throwable) {
            $this->dbConn->rollBack();
            throw $throwable;
        }
    }

    public function findById($id)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM `workflows` WHERE `id` = ?");
        $stmt->execute([$id]);
        return ($row = $stmt->fetch(PDO::FETCH_ASSOC)) ? $this->buildWorkflowFromRow($row) : null;
    }

    public function findByCorrelationIdAndType($corrId, $type, $parentId = null)
    {
        $conds = ["type = ?", "corr_id = ?"];
        $params = [$type, $corrId];
        if (!is_null($parentId)) {
            array_unshift($conds, "parent_id = ?");
            array_unshift($params, $parentId);
        }

        $stmt = $this->dbConn->prepare("SELECT * FROM `workflows` WHERE " . implode(" AND ", $conds));
        $stmt->execute($params);
        $workflows = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $workflows[] = $this->buildWorkflowFromRow($row);
        }
        return array_filter($workflows);
    }

    public function markAsProceeding($workflowId)
    {
        $stmt = $this->dbConn->prepare("UPDATE `workflows` SET state = ? WHERE `id` = ? AND `state` = ?");
        $stmt->execute([WorkflowState::PROCEEDING, $workflowId, WorkflowState::UNTRIED]);
    }

    public function findProceedingByCorrelationId($corrId)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM `workflows` WHERE `corr_id` = ? AND `state` = ?");
        $stmt->execute([$corrId, WorkflowState::PROCEEDING]);
        return ($row = $stmt->fetch(PDO::FETCH_ASSOC)) ? $this->buildWorkflowFromRow($row) : null;
    }

    public function findProceedingByCorrelationIdAndType($corrId, $type)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM `workflows` WHERE `type` = ? AND `corr_id` = ? AND `state` = ?");
        $stmt->execute([$type, $corrId, WorkflowState::PROCEEDING]);
        $workflows = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $workflows[] = $this->buildWorkflowFromRow($row);
        }
        return $workflows;
    }

    public function findLatestByCorrelationIdAndType($corrId, $type)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM `workflows` WHERE `type` = ? AND `corr_id` = ? ORDER BY `id` DESC LIMIT 1");
        $stmt->execute([$type, $corrId]);
        return ($row = $stmt->fetch(PDO::FETCH_ASSOC)) ? $this->buildWorkflowFromRow($row) : null;
    }

    public function findLatestStartedByCorrelationIdAndType(int $corrId, string $type)
    {
        $stmt = $this->dbConn->prepare(
            "SELECT * FROM `workflows` WHERE `type` = ? AND `corr_id` = ? AND `state` IN (?, ?, ?) 
            ORDER BY `started_at` DESC LIMIT 1"
        );
        $stmt->execute([
            $type,
            $corrId,
            WorkflowState::UNTRIED,
            WorkflowState::PROCEEDING,
            WorkflowState::COMPLETED,
        ]);
        return ($row = $stmt->fetch(PDO::FETCH_ASSOC)) ? $this->buildWorkflowFromRow($row) : null;
    }

    public function findLastCompletedByCorrelationIdAndType($corrId, $type)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM `workflows` WHERE `type` = ? AND `corr_id` = ? AND `state` = ? ORDER BY `id` DESC LIMIT 1");
        $stmt->execute([$type, $corrId, WorkflowState::COMPLETED]);
        return ($row = $stmt->fetch(PDO::FETCH_ASSOC)) ? $this->buildWorkflowFromRow($row) : null;
    }

    public function findAllPending()
    {
        $states = [WorkflowState::UNTRIED, WorkflowState::PROCEEDING];
        $stmt = $this->dbConn->prepare(
            sprintf(
                "SELECT * FROM `workflows` WHERE `state` IN (%s) AND `scheduled_at` <= NOW()",
                implode(', ', array_fill(0, sizeof($states), '?'))
            )
        );
        $stmt->execute($states);
        $pending = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $pending[] = $this->buildWorkflowFromRow($row);
        }
        return array_filter($pending);
    }

    /**
     * @param array $ids
     * @return array
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function findAllByParentIds(array $ids): array
    {
        $sql = sprintf(
            'SELECT * FROM workflows WHERE parent_id IN (%s) OR context REGEXP "\\"parentId\\":(%s)"',
            implode(', ', $ids),
            implode('|', $ids)
        );

        $stmt = $this->dbConn->prepare($sql);
        $result = $stmt->executeQuery();
        $workflowsData = $result->fetchAllAssociative();

        $workflows = [];
        foreach ($workflowsData as $workflowData) {
            $workflows[] = $this->buildWorkflowFromRow($workflowData);
        }

        return $workflows;
    }

    public function countFailed()
    {
        $stmt = $this->dbConn->prepare("
            SELECT COUNT(*) FROM workflows
            WHERE state = ?;
        ");

        $stmt->execute([WorkflowState::FAILED]);

        $failedWorkflows = $stmt->fetch(PDO::FETCH_COLUMN);
        return intval($failedWorkflows);
    }

    public function isPending($type, $accNo)
    {
        $states = [WorkflowState::UNTRIED, WorkflowState::PROCEEDING];
        $stmt = $this->dbConn->prepare(
            sprintf(
                "SELECT * FROM `workflows` WHERE `type` = ? AND `corr_id` = ? AND `state` IN (%s)",
                implode(', ', array_fill(0, sizeof($states), '?'))
            )
        );
        $stmt->execute(array_merge([$type, $accNo], $states));
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPendingTypes($accNo)
    {
        $ret = [];
        $states = [WorkflowState::UNTRIED, WorkflowState::PROCEEDING];
        $stmt = $this->dbConn->prepare(
            sprintf(
                "SELECT `type` FROM `workflows` WHERE `corr_id` = ? AND `state` IN (%s) ORDER BY `id`",
                implode(', ', array_fill(0, sizeof($states), '?'))
            )
        );
        $stmt->execute(array_merge([$accNo], $states));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ret[] = $row['type'];
        }
        return $ret;
    }


    public function findAllProceedInPeriod(\DateTime $from, \DateTime $to, array $type): array
    {
        $stmt = $this->dbConn->prepare(
            sprintf(
                "SELECT * FROM `workflows` WHERE finished_at >= ? AND finished_at <= ? AND `type` IN(%s)",
                implode(', ', array_fill(0, sizeof($type), '?'))
            )
        );
        $params = array_merge([ $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')], $type);
        $stmt->execute($params);
        $result = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $result[] = $this->buildWorkflowFromRow($row);
        }
        return $result;
    }

    private function buildWorkflowFromRow(array $row): AbstractWorkflow
    {
        $workflow = $this->factory->createNewOfType($row["type"]);
        $row["context"] = json_decode($row["context"], true);
        $row["context_init"] = json_decode($row["context_init"], true);
        $workflow->fromArray($row);
        $this->populateWorkflowWithActivities($workflow);
        return $workflow;
    }

    private function populateWorkflowWithActivities(AbstractWorkflow $workflow)
    {
        $stmt = $this->dbConn->prepare("SELECT * FROM `activities` WHERE `workflow_id` = ?");
        $stmt->execute([$workflow->id()]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row["context"] = json_decode($row["context"], true);
            if (!empty($activity = $workflow->getActivity($row["name"]))) {
                $activity->fromArray($row);
            }
        }
    }
}
