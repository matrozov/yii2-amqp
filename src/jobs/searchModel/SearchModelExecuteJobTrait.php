<?php
namespace matrozov\yii2amqp\jobs\searchModel;

/**
 * Trait SearchModelExecuteJobTrait
 * @package matrozov\yii2amqp\traits
 */
trait SearchModelExecuteJobTrait
{
    /**
     * @return SearchModelResponseJob
     */
    public function execute()
    {
        $response = new SearchModelResponseJob();

        /* @var SearchModelExecuteJob $this */
        $response->success = $this->validate();

        /* @var SearchModelExecuteJob $this */
        $response->items   = $this->search();

        /* @var SearchModelExecuteJob $this */
        $response->errors  = $this->getErrors();

        return $response;
    }
}