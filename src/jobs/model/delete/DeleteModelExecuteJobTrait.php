<?php
namespace matrozov\yii2amqp\jobs\model\delete;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelExecuteJobTrait;
use yii\base\ErrorException;

/**
 * Trait DeleteModelExecuteJobTrait
 * @package matrozov\yii2amqp\jobs\model\delete
 */
trait DeleteModelExecuteJobTrait
{
    use ModelExecuteJobTrait;

    /**
     * @param Connection  $connection
     * @param AmqpMessage $message
     *
     * @return mixed
     * @throws ErrorException
     */
    public function executeModel(Connection $connection, AmqpMessage $message)
    {
        /* @var DeleteModelExecuteJob $this */
        $result = $this->executeDelete($connection, $message);

        if (!is_bool($result) && !is_int($result)) {
            throw new ErrorException('Result must be boolean or integer!');
        }

        return $result;
    }
}