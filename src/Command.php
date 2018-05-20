<?php
namespace matrozov\yii2amqp;

use yii\console\Controller;

/**
 * Class Command
 * @package matrozov\yii2amqp
 *
 * @property Connection $connection
 * @property int $timeout
 */
class Command extends Controller
{
    /* @var Connection $connection */
    public $connection;

    /* @var int $timeout */
    public $timeout = 0;

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        $options = parent::options($actionID);

        $options[] = 'timeout';

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            't' => 'timeout',
        ]);
    }

    public function actionListen()
    {
        $queueNames = func_get_args();

        $this->connection->listen($queueNames, $this->timeout);
    }
}