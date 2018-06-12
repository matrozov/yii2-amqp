<?php
namespace matrozov\yii2amqp\jobs\model\search;

use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;
use matrozov\yii2amqp\jobs\simple\BaseJobTrait;

/**
 * Interface ModelSearchInternalResponseJob
 * @package matrozov\yii2amqp\jobs
 */
class ModelSearchInternalResponseJob implements RpcResponseJob
{
    use BaseJobTrait;

    public $success;
    public $items;
    public $errors;
}