<?php

namespace matrozov\yii2amqp\exceptions;

use yii\base\Exception;

class RpcException extends Exception
{
    protected $className;
    protected $trace;

    public function __construct($className, $message, $code, $file, $line, $trace)
    {
        parent::__construct($message, $code);


    }

    public function getTrace()
    {

    }
}