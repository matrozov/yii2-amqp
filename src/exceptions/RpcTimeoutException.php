<?php

namespace matrozov\yii2amqp\exceptions;

use Exception;
use yii\web\HttpException;

/**
 * Class RpcTimeoutException
 * @package matrozov\yii2amqp\exceptions
 */
class RpcTimeoutException extends HttpException
{
    /**
     * RpcTimeoutException constructor.
     *
     * @param string|null    $message
     * @param int            $code
     * @param Exception|null $previous
     */
    public function __construct($message = null, $code = 0, Exception $previous = null)
    {
        parent::__construct(504, $message, $code, $previous);
    }
}
