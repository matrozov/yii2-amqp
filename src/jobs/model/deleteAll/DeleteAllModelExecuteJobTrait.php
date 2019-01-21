<?php
namespace matrozov\yii2amqp\jobs\model\deleteAll;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelExecuteJobTrait;
use yii\base\ErrorException;

/**
 * Trait DeleteAllModelExecuteJobTrait
 * @package matrozov\yii2amqp\jobs\model\deleteAll
 */
trait DeleteAllModelExecuteJobTrait
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
        /* @var DeleteAllModelExecuteJob $this */
        $result = $this->executeDeleteAll($connection, $message);

        if ($result === false) {
            return false;
        }

        if (!is_int($result)) {
            throw new ErrorException('Result must be integer!');
        }

        return $result;
    }
}