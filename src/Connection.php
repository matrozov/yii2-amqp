<?php
namespace matrozov\yii2amqp;

use Yii;
use yii\base\Application as BaseApp;
use yii\console\Application as ConsoleApp;
use yii\di\Instance;
use yii\base\Event;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\base\BootstrapInterface;
use yii\base\ErrorException;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\PsrDestination;
use Enqueue\AmqpLib\AmqpConnectionFactory;
use matrozov\yii2amqp\jobs\BaseJob;
use matrozov\yii2amqp\jobs\simple\RequestJob;
use matrozov\yii2amqp\jobs\simple\ExecuteJob;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;
use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;
use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;
use matrozov\yii2amqp\jobs\rpc\RpcFalseResponseJob;
use matrozov\yii2amqp\serializers\JsonSerializer;
use matrozov\yii2amqp\serializers\Serializer;

/**
 * Class Connection
 * @package matrozov\yii2amqp
 *
 * @property string|null    $dsn
 * @property string|null    $host
 * @property int|null       $port
 * @property string|null    $user
 * @property string|null    $password
 * @property string|null    $vhost
 *
 * @property float|null     $readTimeout
 * @property float|null     $writeTimeout
 * @property float|null     $connectionTimeout
 *
 * @property float|null     $heartbeat
 * @property bool|null      $persisted
 * @property bool|null      $lazy
 *
 * @property bool|null      $qosGlobal
 * @property int|null       $qosPrefetchSize
 * @property int|null       $qosPrefetchCount
 *
 * @property bool|null      $sslOn
 * @property bool|null      $sslVerify
 * @property string|null    $sslCacert
 * @property string|null    $sslCert
 * @property string|null    $sslKey
 *
 * @property int            $maxAttempts
 *
 * @property []array        $exchanges
 * @property []array        $queues
 * @property []array        $bindings
 *
 * @property []array        $defaultQueue
 * @property []array        $defaultExchange
 * @property []array        $defaultBind
 *
 * @property int            $rpcTimeout
 *
 * @property Serializer     $serializer
 */
class Connection extends BaseObject implements BootstrapInterface
{
    const ATTEMPT    = 'amqp-attempt';

    /**
     * The connection to the borker could be configured as an array of options
     * or as a DSN string like amqp:, amqps:, amqps://user:pass@localhost:1000/vhost.
     *
     * @var string
     */
    public $dsn;

    /**
     * The message queue broker's host.
     *
     * @var string|null
     */
    public $host;

    /**
     * The message queue broker's port.
     *
     * @var string|null
     */
    public $port;

    /**
     * This is RabbitMQ user which is used to login on the broker.
     *
     * @var string|null
     */
    public $user;

    /**
     * This is RabbitMQ password which is used to login on the broker.
     *
     * @var string|null
     */
    public $password;

    /**
     * Virtual hosts provide logical grouping and separation of resources.
     *
     * @var string|null
     */
    public $vhost;


    /**
     * The time PHP socket waits for an information while reading. In seconds.
     *
     * @var float|null
     */
    public $readTimeout;

    /**
     * The time PHP socket waits for an information while witting. In seconds.
     *
     * @var float|null
     */
    public $writeTimeout;

    /**
     * The time RabbitMQ keeps the connection on idle. In seconds.
     *
     * @var float|null
     */
    public $connectionTimeout;


    /**
     * The periods of time PHP pings the broker in order to prolong the connection timeout. In seconds.
     *
     * @var float|null
     */
    public $heartbeat;

    /**
     * PHP uses one shared connection if set true.
     *
     * @var bool|null
     */
    public $persisted;

    /**
     * The connection will be established as later as possible if set true.
     *
     * @var bool|null
     */
    public $lazy;


    /**
     * If false prefetch_count option applied separately to each new consumer on the channel
     * If true prefetch_count option shared across all consumers on the channel.
     *
     * @var bool|null
     */
    public $qosGlobal;

    /**
     * Defines number of message pre-fetched in advance on a channel basis.
     *
     * @var int|null
     */
    public $qosPrefetchSize;

    /**
     * Defines number of message pre-fetched in advance per consumer.
     *
     * @var int|null
     */
    public $qosPrefetchCount;


    /**
     * Defines whether secure connection should be used or not.
     *
     * @var bool|null
     */
    public $sslOn;

    /**
     * Require verification of SSL certificate used.
     *
     * @var bool|null
     */
    public $sslVerify;

    /**
     * Location of Certificate Authority file on local filesystem which should be used with the verify_peer context option to authenticate the identity of the remote peer.
     *
     * @var string|null
     */
    public $sslCacert;

    /**
     * Path to local certificate file on filesystem.
     *
     * @var string|null
     */
    public $sslCert;

    /**
     * Path to local private key file on filesystem in case of separate files for certificate (local_cert) and private key.
     *
     * @var string|null
     */
    public $sslKey;


    /**
     * Max attempts to requeue message
     *
     * @var int $maxAttempts
     */
    public $maxAttempts = 1;


    /**
     * Queue config list
     *
     * @var []array $queues
     */
    public $queues = [];

    /**
     * Exchange config list
     *
     * @var []array $exchanges
     */
    public $exchanges = [];

    /**
     * Binding config list
     *
     * @var []array $bindings
     */
    public $bindings = [];

    /**
     * Default Queue config
     *
     * @var []array $defaultQueue
     */
    public $defaultQueue = [
        'flags' => AmqpQueue::FLAG_DURABLE,
    ];

    /**
     * Default Exchange config
     *
     * @var []array $defaultExchange
     */
    public $defaultExchange = [
        'type'  => AmqpTopic::TYPE_DIRECT,
        'flags' => AmqpTopic::FLAG_DURABLE,
    ];

    /**
     * Default Bind config
     *
     * @var []array $defaultBind
     */
    public $defaultBind = [
        'routingKey' => null,
        'flags'      => AmqpBind::FLAG_NOPARAM,
        'arguments'  => [],
    ];

    /**
     * Default wait rpc response timeout
     *
     * @var int $rpcTimeout
     */
    public $rpcTimeout = 5000;

    /**
     * @var Serializer $serializer
     */
    public $serializer = JsonSerializer::class;

    /**
     * @var AmqpContext $_context
     */
    protected $_context;

    /**
     * AmqpQueue list
     *
     * @var []AmqpQueue $_queues
     */
    protected $_queues = [];

    /**
     * AmqpTopic list
     *
     * @var []AmqpTopic $_exchanges
     */
    protected $_exchanges = [];


    /**
     * @var Connection $_instance
     */
    protected static $_instance;


    /**
     * @inheritdoc
     * @throws
     */
    public function init()
    {
        parent::init();

        $this->serializer = Instance::ensure($this->serializer, Serializer::class);

        Event::on(BaseApp::class, BaseApp::EVENT_AFTER_REQUEST, function () {
            $this->close();
        });

        self::$_instance = $this;
    }

    /**
     * @param Connection|null $connection
     *
     * @return Connection
     * @throws
     */
    public static function instance(Connection $connection = null)
    {
        if ($connection == null) {
            if (self::$_instance == null) {
                Yii::$app->amqp;
            }

            $connection = self::$_instance;
        }

        if (!$connection || !($connection instanceof Connection)) {
            throw new ErrorException('Can\'t get connection!');
        }

        return $connection;
    }

    /**
     * @return string command id
     * @throws
     */
    protected function getCommandId()
    {
        foreach (Yii::$app->getComponents(false) as $id => $component) {
            if ($component === $this) {
                return Inflector::camel2id($id);
            }
        }

        throw new InvalidConfigException('Amqp must be an application component!');
    }

    /**
     * @inheritdoc
     * @throws
     */
    public function bootstrap($app)
    {
        if ((!$app instanceof ConsoleApp)) {
            return;
        }

        $app->controllerMap[$this->getCommandId()] = [
            'class' => Command::class,
            'connection' => $this,
        ];
    }

    /**
     * Open amqp connection
     *
     * @throws
     */
    public function open()
    {
        if ($this->_context) {
            $this->close();
        }

        $config = [
            'dsn'                => $this->dsn,
            'host'               => $this->host,
            'port'               => $this->port,
            'user'               => $this->user,
            'pass'               => $this->password,
            'vhost'              => $this->vhost,

            'read_timeout'       => $this->readTimeout,
            'write_timeout'      => $this->writeTimeout,
            'connection_timeout' => $this->connectionTimeout,

            'heartbeat'          => $this->heartbeat,
            'persisted'          => $this->persisted,
            'lazy'               => $this->lazy,

            'qos_global'         => $this->qosGlobal,
            'qos_prefetch_size'  => $this->qosPrefetchSize,
            'qos_prefetch_count' => $this->qosPrefetchCount,

            'ssl_on'             => $this->sslOn,
            'ssl_verify'         => $this->sslVerify,
            'ssl_cacert'         => $this->sslCacert,
            'ssl_cert'           => $this->sslCert,
            'ssl_key'            => $this->sslKey,
        ];

        $config = array_filter($config, function($value) {
            return null !== $value;
        });

        $factory = new AmqpConnectionFactory($config);

        $this->_context = $factory->createContext();

        $this->setup();
    }

    /**
     * Close amqp connection
     */
    public function close()
    {
        if (!$this->_context) {
            return;
        }

        $this->_context->close();
        $this->_context = null;
    }

    /**
     * Setup queues, exchanges and bindings
     *
     * @throws
     */
    protected function setup()
    {
        foreach ($this->queues as $queueConfig) {
            $queueConfig = ArrayHelper::merge($this->defaultQueue, $queueConfig);

            foreach (['name', 'flags'] as $field) {
                if (!key_exists($field, $queueConfig)) {
                    throw new InvalidConfigException('Queue config must contain `' . $field . '` field');
                }
            }

            $queue = $this->_context->createQueue($queueConfig['name']);
            $queue->addFlag($queueConfig['flags']);
            $this->_context->declareQueue($queue);

            $this->_queues[$queueConfig['name']] = $queue;
        }

        foreach ($this->exchanges as $exchangeConfig) {
            $exchangeConfig = ArrayHelper::merge($this->defaultExchange, $exchangeConfig);

            foreach (['name', 'type', 'flags'] as $field) {
                if (!key_exists($field, $exchangeConfig)) {
                    throw new InvalidConfigException('Exchange config must contain `' . $field . '` field');
                }
            }

            $exchange = $this->_context->createTopic($exchangeConfig['name']);
            $exchange->setType($exchangeConfig['type']);
            $exchange->addFlag($exchangeConfig['flags']);
            $this->_context->declareTopic($exchange);

            $this->_exchanges[$exchangeConfig['name']] = $exchange;
        }

        foreach ($this->bindings as $bindConfig) {
            $bindConfig = ArrayHelper::merge($this->defaultBind, $bindConfig);

            foreach (['queue', 'exchange', 'routingKey', 'flags', 'arguments'] as $field) {
                if (!key_exists($field, $bindConfig)) {
                    throw new InvalidConfigException('Bind config must contain `' . $field . '` field');
                }
            }

            if (!isset($this->_queues[$bindConfig['queue']])) {
                throw new ErrorException('Can\'t bind unknown Queue!');
            }

            if (!isset($this->_exchanges[$bindConfig['exchange']])) {
                throw new ErrorException('Can\'t bind unknown Exchange!');
            }

            $this->_context->bind(new AmqpBind(
                $this->_queues[$bindConfig['queue']],
                $this->_exchanges[$bindConfig['exchange']],
                $bindConfig['routingKey'],
                $bindConfig['flags'],
                $bindConfig['arguments']
            ));
        }
    }

    /**
     * @param BaseJob $job
     *
     * @return AmqpMessage
     * @throws
     */
    protected function createMessage(BaseJob $job)
    {
        $message = $this->_context->createMessage();

        $message->setDeliveryMode($job->deliveryMode());
        $message->setMessageId(uniqid('', true));
        $message->setTimestamp(time());
        $message->setProperty(self::ATTEMPT, 1);

        $message->setBody($this->serializer->serialize($job));

        if (!empty($this->serializer->contentType())) {
            $message->setContentType($this->serializer->contentType());
        }

        return $message;
    }

    /**
     * @param PsrDestination $target
     * @param RpcRequestJob  $job
     *
     * @return RpcResponseJob|bool|null
     * @throws
     */
    protected function internalRpcSend(PsrDestination $target, RpcRequestJob $job)
    {
        $message = $this->createMessage($job);

        $queue = $this->_context->createQueue(uniqid('', true));
        $queue->addFlag(AmqpQueue::FLAG_IFUNUSED);
        $queue->addFlag(AmqpQueue::FLAG_AUTODELETE);
        $queue->addFlag(AmqpQueue::FLAG_EXCLUSIVE);
        $this->_context->declareQueue($queue);

        $message->setReplyTo($queue->getQueueName());
        $message->setCorrelationId(uniqid('', true));

        $producer = $this->_context->createProducer();
        $producer->send($target, $message);

        $consumer = $this->_context->createConsumer($queue);

        while (true) {
            $responseMessage = $consumer->receive($this->rpcTimeout);

            if (!$responseMessage) {
                return null;
            }

            if (!$message->getCorrelationId() != $responseMessage->getCorrelationId()) {
                $consumer->reject($responseMessage, false);

                continue;
            }

            $consumer->acknowledge($responseMessage);

            $responseJob = $this->serializer->deserialize($responseMessage->getBody());

            if (!($responseJob instanceof RpcResponseJob)) {
                throw new ErrorException('Root object must be RpcResponseJob!');
            }

            if ($responseJob instanceof RpcFalseResponseJob) {
                return false;
            }

            return $responseJob;
        }

        return null;
    }

    /**
     * @param PsrDestination $target
     * @param BaseJob        $job
     *
     * @return bool
     * @throws
     */
    protected function internalSimpleSend(PsrDestination $target, BaseJob $job)
    {
        $message = $this->createMessage($job);

        $producer = $this->_context->createProducer();
        $producer->send($target, $message);

        return true;
    }

    /**
     * @param RequestJob $job
     * @param string     $exchangeName
     *
     * @return RpcResponseJob|bool|null
     * @throws
     */
    public function send(RequestJob $job, $exchangeName = null)
    {
        $this->open();

        if ($exchangeName === null) {
            $exchangeName = $job::exchangeName();
        }

        if (!isset($this->_exchanges[$exchangeName])) {
            throw new ErrorException('Exchange with name `' . $exchangeName . '` not found!');
        }

        $exchange = $this->_exchanges[$exchangeName];

        if ($job instanceof RpcRequestJob) {
            return $this->internalRpcSend($exchange, $job);
        }
        else {
            return $this->internalSimpleSend($exchange, $job);
        }
    }

    /**
     * @param AmqpMessage    $message
     * @param RpcResponseJob $responseJob
     *
     * @return bool
     */
    protected function replyRpcMessage(AmqpMessage $message, RpcResponseJob $responseJob)
    {
        $queueName = $message->getReplyTo();

        $queue = $this->_context->createQueue($queueName);

        return $this->internalSimpleSend($queue, $responseJob);
    }

    /**
     * @param RpcExecuteJob $job
     * @param AmqpMessage   $message
     * @param AmqpConsumer  $consumer
     *
     * @throws
     */
    protected function handleRpcMessage(RpcExecuteJob $job, AmqpMessage $message, AmqpConsumer $consumer)
    {
        try {
            $responseJob = $job->execute();

            if (!$responseJob) {
                $responseJob = new RpcFalseResponseJob();
            }

            if (!($responseJob instanceof RpcResponseJob)) {
                throw new ErrorException('You must return response RpcResponseJob for RpcRequestJob!');
            }

            $this->replyRpcMessage($message, $responseJob);
        }
        catch (\Exception $e) {
            if ($this->redelivery($message, $consumer)) {
                $consumer->acknowledge($message);
            }
            else {
                $responseJob = new RpcFalseResponseJob();

                $this->replyRpcMessage($message, $responseJob);

                $consumer->reject($message, false);
            }

            throw $e;
        }

        $consumer->acknowledge($message);
    }

    /**
     * @param ExecuteJob   $job
     * @param AmqpMessage  $message
     * @param AmqpConsumer $consumer
     *
     * @throws
     */
    protected function handleSimpleMessage(ExecuteJob $job, AmqpMessage $message, AmqpConsumer $consumer)
    {
        try {
            $job->execute();
        }
        catch (\Exception $e) {
            if ($this->redelivery($message, $consumer)) {
                $consumer->acknowledge($message);
            }
            else {
                $consumer->reject($message, false);
            }

            throw $e;
        }

        $consumer->acknowledge($message);
    }

    /**
     * @param AmqpMessage  $message
     * @param AmqpConsumer $consumer
     *
     * @return bool
     * @throws
     */
    protected function handleMessage(AmqpMessage $message, AmqpConsumer $consumer)
    {
        $job = $this->serializer->deserialize($message->getBody());

        /* @var ExecuteJob $job */
        if (!($job instanceof ExecuteJob)) {
            $consumer->reject($message, false);

            throw new ErrorException('Can\'t execute unknown job type');
        }

        if ($job instanceof RpcExecuteJob) {
            $this->handleRpcMessage($job, $message, $consumer);
        }
        else {
            $this->handleSimpleMessage($job, $message, $consumer);
        }
    }

    /**
     * @param []string|string|null $queueNames
     * @param int  $timeout
     *
     * @throws
     */
    public function listen($queueNames = null, $timeout = 0)
    {
        $this->open();

        if (empty($queueNames)) {
            $queueNames = array_keys($this->_queues);
        }

        foreach ($queueNames as $queueName) {
            if (!isset($this->_queues[$queueName])) {
                throw new ErrorException('Queue config `' . $queueName . '` not found!');
            }

            $consumer = $this->_context->createConsumer($this->_queues[$queueName]);

            $this->_context->subscribe($consumer, function(AmqpMessage $message, AmqpConsumer $consumer) {
                $this->handleMessage($message, $consumer);

                return true;
            });
        }

        $this->_context->consume($timeout);
    }

    /**
     * @param AmqpMessage $message
     *
     * @return bool
     * @throws
     */
    protected function redelivery(AmqpMessage $message, AmqpConsumer $consumer)
    {
        $attempt = $message->getProperty(self::ATTEMPT, 1);

        if ($attempt >= $this->maxAttempts) {
            return false;
        }

        $newMessage = $this->_context->createMessage($message->getBody(), $message->getProperties(), $message->getHeaders());
        $newMessage->setDeliveryMode($message->getDeliveryMode());

        $newMessage->setProperty(self::ATTEMPT, ++$attempt);

        $produces = $this->_context->createProducer();
        $produces->send($consumer->getQueue(), $newMessage);

        return true;
    }
}