<?php
namespace matrozov\yii2amqp\jobs\rpc;

use yii\web\HttpException;

/**
 * Class RpcExceptionResponseJob
 * @package matrozov\yii2amqp\jobs\rpc
 *
 * @property integer $code
 * @property string  $message
 *
 * @property boolean $httpException
 * @property integer $statusCode
 */
class RpcExceptionResponseJob implements RpcResponseJob
{
    public $code;
    public $message;

    public $httpException = false;
    public $statusCode    = 500;

    /**
     * @param \Exception $e
     */
    public function fillByException(\Exception $e)
    {
        $this->code    = $e->getCode();
        $this->message = $e->getMessage();

        if ($e instanceof HttpException) {
            $this->httpException = true;
            $this->statusCode    = $e->statusCode;
        }
    }

    /**
     * @return \Exception
     */
    public function exception()
    {
        if ($this->httpException) {
            return new HttpException($this->statusCode, $this->message, $this->code);
        }

        return new \Exception($this->message, $this->code);
    }
}