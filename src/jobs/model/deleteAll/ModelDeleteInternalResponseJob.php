<?php
namespace matrozov\yii2amqp\jobs\model\deleteAll;

use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;

/**
 * Class ModelDeleteInternalResponseJob
 * @package matrozov\yii2amqp\jobs\model\deleteAll
 *
 * @property integer $affected
 */
class ModelDeleteInternalResponseJob implements RpcResponseJob
{
    public $affected;
}