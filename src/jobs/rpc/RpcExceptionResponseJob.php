<?php
namespace matrozov\yii2amqp\jobs\rpc;

class RpcExceptionResponseJob implements RpcResponseJob
{
    public $code;
    public $message;
}