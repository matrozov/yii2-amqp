<?php

namespace matrozov\yii2amqp\debugger\targets;

use matrozov\yii2amqp\debugger\Target;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\elasticsearch\Connection;
use yii\elasticsearch\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

/**
 * Class ElasticsearchTarget
 * @package matrozov\yii2amqp\debugger\targets
 *
 * @property Connection|array|string $db
 * @property string                  $index
 * @property array                   $extraFields
 */
class ElasticsearchTarget extends Target
{
    const CONNECTION_COUNT = 10;

    const JSON_PARAMS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE;

    public $db          = 'elasticsearch';
    public $index       = 'yii';
    public $extraFields = [];

    /** @var resource */
    protected $_curl;
    /** @var resource[] */
    protected $_free = [];
    /** @var resource[] */
    protected $_used = [];

    /**
     * @throws ErrorException
     */
    public function __destruct()
    {
        $this->shutdown();
    }

    /**
     * @inheritDoc
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        $this->db = Instance::ensure($this->db, Connection::class);

        $this->_curl = curl_multi_init();

        for ($i = 0; $i < static::CONNECTION_COUNT; $i++) {
            $this->_free[] = curl_init();
        }
    }

    /**
     * @param float $timeout
     * @param bool  $waitAll
     * @return bool
     */
    protected function wait(float $timeout = 1.0, bool $waitAll = false): bool
    {
        if (empty($this->_used)) {
            return true;
        }

        do {
            curl_multi_exec($this->_curl, $running);
            curl_multi_select($this->_curl, $timeout);
        } while ($running && $waitAll);

        while (true) {
            $done = curl_multi_info_read($this->_curl);

            if (!$done) {
                break;
            }

            $curl = $done['handle'];

            ArrayHelper::removeValue($this->_used, $curl);

            curl_multi_remove_handle($this->_curl, $curl);

            $this->_free[] = $curl;
        }

        return !empty($this->_free);
    }

    /**
     * @param string $body
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidConfigException
     */
    protected function add(string $body)
    {
        if (empty($this->_free)) {
            if (!$this->wait(30)) {
                throw new ErrorException('Can\'t get free connection');
            }
        }

        $this->db->open();

        $node     = $this->db->nodes[$this->db->activeNode];
        $protocol = isset($node['protocol']) ? $node['protocol'] : $this->db->defaultProtocol;
        $host     = $node['http_address'];

        if (strncmp($host, 'inet[', 5) == 0) {
            $host = substr($host, 5, -1);

            if (($pos = strpos($host, '/')) !== false) {
                $host = substr($host, $pos + 1);
            }
        }

        $url = $protocol . '://' . $host . '/_bulk';

        $curl = array_pop($this->_free);

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FORBID_REUSE   => false,
            CURLOPT_NOBODY         => false,
            CURLOPT_HTTPHEADER     => [
                'Expect:',
                'Content-Type: application/json',
            ],
        ]);

        if (!empty($this->db->auth) || isset($node['auth']) && $node['auth'] !== false) {
            $auth = $node['auth'] ?: $this->db->auth;

            if (empty($auth['username'])) {
                throw new InvalidConfigException('Username is required to use authentication');
            }

            if (empty($auth['password'])) {
                throw new InvalidConfigException('Password is required to use authentication');
            }

            curl_setopt_array($curl, [
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD  => $auth['username'] . ':' . $auth['password'],
            ]);
        }

        if ($this->db->connectionTimeout !== null) {
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->db->connectionTimeout);
        }

        if ($this->db->dataTimeout !== null) {
            curl_setopt($curl, CURLOPT_TIMEOUT, $this->db->dataTimeout);
        }

        curl_multi_add_handle($this->_curl, $curl);
        curl_multi_exec($this->_curl, $running);

        $this->_used[] = $curl;
    }

    /**
     * @param array $data
     */
    protected function prepareExtraFields(array &$data)
    {
        foreach ($this->extraFields as $name => $value) {
            if (is_callable($value)) {
                $data[$name] = call_user_func($value, $data);
            } else {
                $data[$name] = $value;
            }
        }
    }

    /**
     * @inheritDoc
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function logStart(string $type, string $id, array $data): void
    {
        $body = '';

        $body .= Json::encode([
            'update' => [
                '_type'  => '_doc',
                '_index' => $this->index,
                '_id'    => $id,
            ],
        ], self::JSON_PARAMS) . PHP_EOL;

        $this->prepareExtraFields($data);

        $body .= Json::encode([
            'doc' => [
                '@timestamp' => date('c'),
                'type'       => $type,
                'start'      => $data,
            ],
            'doc_as_upsert' => true,
        ], self::JSON_PARAMS) . PHP_EOL;

        $this->add($body);
    }

    /**
     * @inheritDoc
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function logEnd(string $type, string $id, array $data): void
    {
        $body = '';

        $body .= Json::encode([
            'update' => [
                '_type'  => '_doc',
                '_index' => $this->index,
                '_id'    => $id,
            ],
        ], self::JSON_PARAMS) . PHP_EOL;

        $body .= Json::encode([
            'doc' => [
                '@timestamp' => date('c'),
                'type'       => $type,
                'end'        => $data,
            ],
            'doc_as_upsert' => true,
        ], self::JSON_PARAMS) . PHP_EOL;

        $this->add($body);
    }

    /**
     * @inheritDoc
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function log(string $type, array $data): void
    {
        $body = '';

        $body .= Json::encode([
            'create' => [
                '_type'  => '_doc',
                '_index' => $this->index,
            ],
        ], self::JSON_PARAMS) . PHP_EOL;

        $this->prepareExtraFields($data);

        $body .= Json::encode([
            '@timestamp' => date('c'),
            'type'       => $type,
            'log'        => $data,
        ], self::JSON_PARAMS) . PHP_EOL;

        $this->add($body);
    }

    /**
     * @inheritDoc
     */
    public function flush()
    {

    }

    /**
     * @inheritDoc
     * @throws ErrorException
     */
    public function shutdown()
    {
        if (!$this->_curl) {
            return;
        }

        if (!$this->wait(30, true)) {
            throw new ErrorException('Connection close timeout');
        }

        foreach ($this->_used as $connection) {
            curl_close($connection);
        }

        foreach ($this->_free as $connection) {
            curl_close($connection);
        }

        curl_multi_close($this->_curl);
    }
}
