<?php

namespace matrozov\yii2amqp\debugger\targets;

use matrozov\yii2amqp\debugger\Target;
use Yii;
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

    const CACHE_INDEX_NAME     = 'elastic_index_by_alias-%s';
    const CACHE_INDEX_NAME_TTL = 60;

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

        //$this->_curl = curl_multi_init();
        $this->_curl = curl_init();

        for ($i = 0; $i < static::CONNECTION_COUNT; $i++) {
            //$this->_free[] = curl_init();
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

            $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

            if ($code != 200) {
                $content = curl_multi_getcontent($curl);

                Yii::warning('Elasticsearch target log error: ' . mb_substr($content, 0, 200));
            }

            ArrayHelper::removeValue($this->_used, $curl);

            curl_multi_remove_handle($this->_curl, $curl);

            $this->_free[] = $curl;
        }

        return !empty($this->_free);
    }

    /**
     * @param string $url
     * @param string|null $body
     * @return bool|string
     * @throws Exception
     * @throws InvalidConfigException
     */
    protected function request(string $url, ?string $body = null)
    {
        $this->db->open();

//        if (empty($this->_free)) {
//            if (!$this->wait(30)) {
//                throw new ErrorException('Can\'t get free connection');
//            }
//        }

        $node     = $this->db->nodes[$this->db->activeNode];
        $protocol = isset($node['protocol']) ? $node['protocol'] : $this->db->defaultProtocol;
        $host     = $node['http_address'];

        if (strncmp($host, 'inet[', 5) == 0) {
            $host = substr($host, 5, -1);

            if (($pos = strpos($host, '/')) !== false) {
                $host = substr($host, $pos + 1);
            }
        }

        $url = $protocol . '://' . $host . $url;

        //$curl = array_pop($this->_free);
        $curl = $this->_curl;

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FORBID_REUSE   => false,
            CURLOPT_NOBODY         => false,
            CURLOPT_HTTPHEADER     => [
                'Expect:',
                'Content-Type: application/json',
            ],
        ]);

        if ($body !== null) {
            curl_setopt_array($curl, [
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => $body,
            ]);
        } else {
            curl_setopt_array($curl, [
                CURLOPT_POST       => false,
            ]);
        }

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

        //curl_multi_add_handle($this->_curl, $curl);
        //curl_multi_exec($this->_curl, $running);

        return curl_exec($curl);

        //$this->_used[] = $curl;
    }

    /**
     * @param string $alias
     * @return string
     * @throws Exception
     * @throws InvalidConfigException
     */
    protected function getIndexName(string $alias): string
    {
        $cache = Yii::$app->getCache();

        if ($cache) {
            $index = $cache->get(sprintf(self::CACHE_INDEX_NAME, $alias));

            if ($index !== false) {
                return $index;
            }
        }

        $response = $this->request('/_cat/aliases/' . $alias . '?format=json');
        $response = Json::decode($response);

        if (!empty($response)) {
            $index = ArrayHelper::getValue(reset($response), 'index');
        } else {
            $index = $alias;
        }

        if ($cache) {
            $cache->set(sprintf(self::CACHE_INDEX_NAME, $alias), $index, self::CACHE_INDEX_NAME_TTL);
        }

        return $index;
    }

    /**
     * @param string $body
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidConfigException
     */
    protected function add(string $body)
    {
        $this->request('/_bulk', $body);
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
                '_type'  => '_doc',
                '_index' => $this->getIndexName($this->index),
                '_id'    => $id,
            ],
        ], self::JSON_PARAMS) . PHP_EOL;

        $this->prepareExtraFields($data);

        $body .= Json::encode([
            '@timestamp' => date('c'),
            'type'       => $type,
            'start'      => $data,
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
                '_index' => $this->getIndexName($this->index),
                '_id'    => $id,
            ],
        ], self::JSON_PARAMS) . PHP_EOL;

        $body .= Json::encode([
            'doc' => [
                'end' => $data,
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
                '_type'  => '_doc',
                '_index' => $this->getIndexName($this->index),
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

        curl_close($this->_curl);

        $this->_curl = null;

//        if (!$this->wait(30, true)) {
//            throw new ErrorException('Connection close timeout');
//        }
//
//        foreach ($this->_used as $connection) {
//            curl_close($connection);
//        }
//
//        $this->_used = [];
//
//        foreach ($this->_free as $connection) {
//            curl_close($connection);
//        }
//
//        $this->_free = [];
//
//        curl_multi_close($this->_curl);
//
//        $this->_curl = null;
    }
}
