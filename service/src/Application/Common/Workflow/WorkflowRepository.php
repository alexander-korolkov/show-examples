<?php

namespace Fxtm\CopyTrading\Application\Common\Workflow;

interface WorkflowRepository
{
    public function store(AbstractWorkflow $workflow);

    /**
     * @param int $id
     *
     * @return AbstractWorkflow
     */
    public function findById($id);

    /**
     * @param int $corrId
     */
    public function findProceedingByCorrelationId($corrId);

    /**
     * @param int $corrId
     * @param string $type
     */
    public function findProceedingByCorrelationIdAndType($corrId, $type);

    /**
     * @param int $corrId
     * @param string $type
     * @param int|null $parentId
     */
    public function findByCorrelationIdAndType($corrId, $type, $parentId = null);

    /**
     * @param int $corrId
     * @param string $type
     * @return AbstractWorkflow|null
     */
    public function findLatestByCorrelationIdAndType($corrId, $type);

    /**
     * @param int $corrId
     * @param string $type
     * @return AbstractWorkflow|null
     */
    public function findLatestStartedByCorrelationIdAndType(int $corrId, string $type);

    /**
     * @param int $corrId
     * @param string $type
     * @return AbstractWorkflow|null
     */
    public function findLastCompletedByCorrelationIdAndType($corrId, $type);

    /**
     * @return AbstractWorkflow[]
     */
    public function findAllPending();

    public function findAllByParentIds(array $ids): array;

    /**
     * @return int number of failed workflows
     */
    public function countFailed();

    /**
     * @param int $workflowId
     */
    public function markAsProceeding($workflowId);

    /**
     * @param string $type
     * @param string $accNo
     * @return bool
     */
    public function isPending($type, $accNo);

    /**
     * @param string $accNo
     * @return array
     */
    public function getPendingTypes($accNo);

    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @param string[] $type
     * @return AbstractWorkflow[]
     */
    public function findAllProceedInPeriod(\DateTime $from, \DateTime $to, array $type) : array;

}
