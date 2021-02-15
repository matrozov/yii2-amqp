<?php

namespace matrozov\yii2amqp\debugger\targets;

use matrozov\yii2amqp\debugger\Target;
use stdClass;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\elasticsearch\Connection;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

/**
 * Class ElasticsearchTarget
 * @package matrozov\yii2amqp\debugger\targets
 *
 * @property string $url
 * @property string $index
 */
class ElasticsearchTarget extends Target
{
    const CONNECTION_COUNT = 10;

    const JSON_PARAMS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE;

    public $url   = '';
    public $index = 'yii';

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

        if (empty($this->url)) {
            throw new InvalidConfigException('Url must be specified!');
        }

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
     * @param string $url
     * @param string $body
     * @throws ErrorException
     */
    protected function add(string $url, string $body)
    {
        if (empty($this->_free)) {
            if (!$this->wait(30)) {
                throw new ErrorException('Can\'t get free connection');
            }
        }

        $curl = array_pop($this->_free);

        curl_setopt_array($curl, [
            CURLOPT_URL        => $url,
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HEADER     => false,
        ]);

        curl_multi_add_handle($this->_curl, $curl);
    }

    /**
     * @inheritDoc
     * @throws ErrorException
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

        $body .= Json::encode([
            '@timestamp' => date('c'),
            'type'       => $type,
            'finished'   => false,
            'data'       => $data,
        ], self::JSON_PARAMS) . PHP_EOL;

        $this->add($this->url . '/' . $this->index . '/_bulk', $body);
    }

    /**
     * @inheritDoc
     * @throws ErrorException
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

        $this->add($this->url . '/' . $this->index . '/_bulk', $body);
    }

    /**
     * @inheritDoc
     * @throws ErrorException
     */
    public function log(string $type, array $data): void
    {
        $body = '';

        $body .= Json::encode([
            'create' => [
                '_type' => '_doc',
            ],
        ], self::JSON_PARAMS) . PHP_EOL;

        $body .= Json::encode([
            '@timestamp' => date('c'),
            'type'       => $type,
            'data'       => $data,
        ], self::JSON_PARAMS) . PHP_EOL;

        $this->add($this->url . '/' . $this->index . '/_bulk', $body);
    }

    /**
     * @inheritDoc
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
