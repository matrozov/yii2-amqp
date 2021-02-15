<?php

namespace matrozov\yii2amqp\debugger\targets;

use matrozov\yii2amqp\debugger\Target;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\elasticsearch\Connection;
use yii\elasticsearch\Exception;
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
     * @return bool
     */
    protected function wait(float $timeout = 1.0): bool
    {
        if (empty($this->_used)) {
            return true;
        }

        do {
            $status = curl_multi_exec($this->_curl, $running);

            if (($timeout !== null) && $running) {
                curl_multi_select($this->_curl, $timeout);
            }
        } while ($running && ($status == CURLM_OK));

        $done = curl_multi_info_read($this->_curl);

        if (!$done) {
            return false;
        }

        $curl = $done['handle'];

        curl_multi_remove_handle($this->_curl, $curl);

        unset($this->_used[$curl]);

        $this->_free[] = $curl;

        return true;
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
            CURLOPT_RETURNTRANSFER => false,
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
            'create' => [
                '_type' => '_doc',
                '_id'   => $id,
            ],
        ], self::JSON_PARAMS) . PHP_EOL;

        $this->prepareExtraFields($data);

        $body .= Json::encode([
            '@timestamp' => date('c'),
            'type'       => $type,
            'finished'   => false,
            'data'       => $data,
        ], self::JSON_PARAMS) . PHP_EOL;

        $this->add($body);
    }

    /**
     * @inheritDoc
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function logEnd(string $id, array $data): void
    {
        $body = '';

        $body .= Json::encode([
            'update' => [
                '_type' => '_doc',
                '_id'   => $id,
            ],
        ], self::JSON_PARAMS) . PHP_EOL;

        $body .= Json::encode([
            'doc' => [
                'finished' => true,
                'data'     => $data,
            ],
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
                '_type' => '_doc',
            ],
        ], self::JSON_PARAMS) . PHP_EOL;

        $this->prepareExtraFields($data);

        $body .= Json::encode([
            '@timestamp' => date('c'),
            'type'       => $type,
            'data'       => $data,
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

        if (!$this->wait(30)) {
            throw new ErrorException('Can\'t ');
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
