<?php
namespace matrozov\yii2amqp\jobs\rpc;

use matrozov\yii2amqp\jobs\simple\BaseJobTrait;

class RpcFalseResponseJob implements RpcResponseJob
{
    use BaseJobTrait;
}