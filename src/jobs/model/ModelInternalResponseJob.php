<?php
namespace matrozov\yii2amqp\jobs\model;

use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;

/**
 * Class ModelInternalResponseJob
 * @package matrozov\yii2amqp\jobs\model\model
 *
 * @property bool  $success
 * @property       $result
 * @property array $errors
 */
class ModelInternalResponseJob implements RpcResponseJob
{
    public $success = false;
    public $result  = null;
    public $errors  = [];
}