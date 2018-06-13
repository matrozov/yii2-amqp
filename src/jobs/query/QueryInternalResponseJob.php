<?php
namespace matrozov\yii2amqp\jobs\query;

use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;

/**
 * Class QueryInternalResponseJob
 * @package matrozov\yii2amqp\jobs\model\query
 *
 * @property bool  $success
 * @property       $result
 * @property array $errors
 */
class QueryInternalResponseJob implements RpcResponseJob
{
    public $success = false;
    public $result  = null;
    public $errors  = [];
}