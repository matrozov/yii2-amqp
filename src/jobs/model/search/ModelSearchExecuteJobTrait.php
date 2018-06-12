<?php
namespace matrozov\yii2amqp\jobs\model\search;

/**
 * Trait ModelSearchExecuteJobTrait
 * @package matrozov\yii2amqp\traits
 */
trait ModelSearchExecuteJobTrait
{
    /**
     * @return ModelSearchInternalResponseJob
     */
    public function execute()
    {
        $response = new ModelSearchInternalResponseJob();

        /* @var ModelSearchExecuteJob $this */
        $response->success = $this->validate();

        if ($response->success) {
            /* @var ModelSearchExecuteJob $this */
            $response->items = $this->executeSearch();
        }

        /* @var ModelSearchExecuteJob $this */
        $response->errors  = $this->getErrors();

        return $response;
    }
}