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
 * @property int    $statusCode
 * @property string $message
 * @property int    $code
 */
class RpcExceptionResponseJob implements RpcResponseJob
{
    public $className;

    public $statusCode;
    public $message;
    public $code;

    public function __construct(Exception $exception = null)
    {
        if ($exception) {
            $this->className = get_class($exception);

            $this->message = $exception->getMessage();
            $this->code    = $exception->getCode();

            if ($exception instanceof HttpException) {
                $this->statusCode = $exception->statusCode;
            }
        }
    }

    /**
     * @return HttpException|Exception
     */
    public function exception()
    {
        if ($this->className instanceof HttpException) {
            return new HttpException($this->statusCode, $this->message, $this->code);
        }

        return new Exception($this->message, $this->code);
    }
}