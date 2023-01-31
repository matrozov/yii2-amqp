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
 * @property int|null   $timeout
 * @property int|null   $max_message
 */
class Command extends Controller
{
    /* @var Connection $connection */
    public $connection;

    /* @var int|null $timeout */
    public $timeout;

    /* @var int|null $max_message */
    public $max_message;

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        $options = parent::options($actionID);

        $options[] = 'timeout';
        $options[] = 'max_message';

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            't' => 'timeout',
            'm' => 'max_message',
        ]);
    }

    /**
     * @throws
     */
    public function actionListen()
    {
        $queueNames = func_get_args();

        $this->connection->listen($queueNames, $this->timeout, $this->max_message);
    }
}
