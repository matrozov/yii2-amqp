<?php

namespace matrozov\yii2amqp;

use Yii;
use yii\console\Controller;
use yii\helpers\Console;

/**
 * Class Command
 * @package matrozov\yii2amqp
 *
 * @property Connection $connection
 * @property int        $timeout
 */
class Command extends Controller
{
    /* @var Connection $connection */
    public $connection;

    /* @var int|null $timeout */
    public $timeout;

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

    /**
     * @throws
     */
    public function actionListen()
    {
        $queueNames = func_get_args();

        $this->connection->listen($queueNames, (int)$this->timeout);
    }
}
