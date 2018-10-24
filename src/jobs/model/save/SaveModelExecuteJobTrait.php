<?php
namespace matrozov\yii2amqp\jobs\model\save;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelExecuteJobTrait;
use yii\base\ErrorException;

/**
 * Trait SaveModelExecuteJobTrait
 * @package matrozov\yii2amqp\jobs\model\save
 */
trait SaveModelExecuteJobTrait
{
    use ModelExecuteJobTrait;

    /**
     * @param Connection  $connection
     * @param AmqpMessage $message
     *
     * @return mixed
     * @throws
     */
    public function executeModel(Connection $connection, AmqpMessage $message)
    {
        /* @var SaveModelExecuteJob $this */
        $result = $this->executeSave($connection, $message);

        if (!is_array($result) && !is_bool($result)) {
            throw new ErrorException('Result must be array or boolean!');
        }

        return $result;
    }
}