<?php
namespace matrozov\yii2amqp\jobs\model\findOne;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelExecuteJobTrait;
use yii\base\ErrorException;

/**
 * Trait FindOneModelExecuteJobTrait
 * @package matrozov\yii2amqp\jobs\model\findOne
 */
trait FindOneModelExecuteJobTrait
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
        /* @var FindOneModelExecuteJob $this */
        $result = $this->executeFindOne($connection, $message);

        if (!is_null($result) && !is_array($result) && !is_object($result)) {
            throw new ErrorException('Result must be array or object!');
        }

        return $result;
    }
}