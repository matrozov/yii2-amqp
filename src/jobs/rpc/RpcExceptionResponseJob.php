<?php

namespace matrozov\yii2amqp\jobs\rpc;

use Exception;
use yii\web\HttpException;

/**
 * Class RpcExceptionResponseJob
 * @package matrozov\yii2amqp\jobs\rpc
 *
 * @property string $className
 *
 * @property int       $statusCode
 * @property string    $message
 * @property mixed|int $code
 */
class RpcExceptionResponseJob implements RpcResponseJob
{
    public $statusCode;
    public $message;
    public $code;

    public function __construct(Exception $exception = null)
    {
        if (!$exception) {
            return;
        }

        $this->statusCode = ($exception instanceof HttpException) ? $exception->statusCode : 500;
        $this->code       = $exception->getCode();
        $this->message    = $exception->getMessage();
    }

    /**
     * @return HttpException|Exception
     */
    public function exception()
    {
        return new HttpException($this->statusCode, $this->message, is_int($this->code) ? $this->code : 0);
    }
}
