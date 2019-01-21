<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelExecuteJobTrait;
use yii\base\ErrorException;

/**
 * Trait FindAllModelExecuteJobTrait
 * @package matrozov\yii2amqp\jobs\model\findAll
 */
trait FindAllModelExecuteJobTrait
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
        /* @var FindAllModelExecuteJob $this */
        $result = $this->executeFindAll($connection, $message);

        if ($result === false) {
            return false;
        }

        if (!is_array($result)) {
            throw new ErrorException('Result must be array!');
        }

        return $result;
    }
}