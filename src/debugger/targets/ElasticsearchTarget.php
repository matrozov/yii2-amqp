<?php

namespace matrozov\yii2amqp\debugger\targets;

use matrozov\yii2amqp\debugger\Target;
use stdClass;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\elasticsearch\Connection;
use yii\helpers\Json;

/**
 * Class ElasticsearchTarget
 * @package matrozov\yii2amqp\debugger\targets
 *
 * @property string                  $index
 * @property string                  $type
 * @property Connection|array|string $db
 * @property array                   $dbOptions
 * @property array                   $extraFields
 */
class ElasticsearchTarget extends Target
{
    public $index       = 'yii';
    public $type        = 'log';
    public $db          = 'elasticsearch';
    public $dbOptions   = [];
    public $extraFields = [];

    protected $_logs = [];

    /**
     * {@inheritdoc}
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        $this->db = Instance::ensure($this->db, Connection::class);
    }

    /**
     * @param array $log
     *
     * @return string
     */
    public function prepareLog($log)
    {
        $result = [
            'type'       => $log['type'],
            '@timestamp' => date('c', $log['time']),
            'content'    => $log['content'],
        ];

        foreach ($this->extraFields as $name => $value) {
            if (is_callable($value)) {
                $result[$name] = call_user_func($value, $log);
            }
            else {
                $result[$name] = $value;
            }
        }

        return implode(PHP_EOL, [
            Json::encode([
                'index' => new stdClass(),
            ]),
            Json::encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function log($type, $content)
    {
        $this->_logs[] = [
            'time'    => microtime(true),
            'type'    => $type,
            'content' => $content,
        ];
    }

    /**
     * {@inheritdoc}
     * @throws
     */
    public function flush()
    {
        if (empty($this->_logs)) {
            return;
        }

        $logs = array_map([$this, 'prepareLog'], $this->_logs);
        $content = implode(PHP_EOL, $logs).PHP_EOL;

        $this->db->post([$this->index, $this->type, '_bulk'], $this->dbOptions, $content);

        $this->_logs = [];
    }
}
