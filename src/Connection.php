<?php
declare(strict_types=1);

namespace matrozov\yii2amqp;

use Enqueue\AmqpBunny\AmqpConnectionFactory as AmqpBunnyConnectionFactory;
use Enqueue\AmqpExt\AmqpConnectionFactory as AmqpExtConnectionFactory;
use Enqueue\AmqpLib\AmqpConnectionFactory as AmqpLibConnectionFactory;
use Enqueue\AmqpTools\DelayStrategyAware;
use Interop\Amqp\AmqpConnectionFactory;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpProducer;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\Exception;
use Interop\Queue\Exception\DeliveryDelayNotSupportedException;
use matrozov\yii2amqp\debugger\Debugger;
use matrozov\yii2amqp\events\ExecuteEvent;
use matrozov\yii2amqp\events\JobEvent;
use matrozov\yii2amqp\events\ResponseEvent;
use matrozov\yii2amqp\events\SendEvent;
use matrozov\yii2amqp\exceptions\NeedRedeliveryException;
use matrozov\yii2amqp\exceptions\RedeliveryException;
use matrozov\yii2amqp\exceptions\RpcTimeoutException;
use matrozov\yii2amqp\exceptions\RpcTransferableException;
use matrozov\yii2amqp\jobs\AccessControlJob;
use matrozov\yii2amqp\jobs\BaseJob;
use matrozov\yii2amqp\jobs\DelayedJob;
use matrozov\yii2amqp\jobs\ExpiredJob;
use matrozov\yii2amqp\jobs\PersistentJob;
use matrozov\yii2amqp\jobs\PriorityJob;
use matrozov\yii2amqp\jobs\RequestNamedJob;
use matrozov\yii2amqp\jobs\RetryableJob;
use matrozov\yii2amqp\jobs\rpc\RpcExceptionResponseJob;
use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;
use matrozov\yii2amqp\jobs\rpc\RpcFalseResponseJob;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;
use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;
use matrozov\yii2amqp\jobs\rpc\RpcSendBatchAsync;
use matrozov\yii2amqp\jobs\simple\ExecuteJob;
use matrozov\yii2amqp\jobs\simple\RequestJob;
use matrozov\yii2amqp\serializers\JsonSerializer;
use matrozov\yii2amqp\serializers\Serializer;
use Throwable;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\console\Application;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\web\HttpException;
use yii\web\Request;

/**
 * Class Connection
 * @package  matrozov\yii2amqp
 *
 * @property AmqpContext       $context
 *
 * @property string|null       $dsn
 * @property string|null       $host
 * @property int|null          $port
 * @property string|null       $user
 * @property string|null       $password
 * @property string|null       $vhost
 *
 * @property bool              $keepalive
 *
 * @property float|null        $readTimeout
 * @property float|null        $writeTimeout
 * @property float|null        $connectionTimeout
 *
 * @property float|null        $heartbeat
 * @property bool|null         $persisted
 * @property bool|null         $lazy
 *
 * @property bool|null         $qosGlobal
 * @property int|null          $qosPrefetchSize
 * @property int|null          $qosPrefetchCount
 *
 * @property bool|null         $sslOn
 * @property bool|null         $sslVerify
 * @property string|null       $sslCacert
 * @property string|null       $sslCert
 * @property string|null       $sslKey
 *
 * @property string            $driver
 *
 * @property int               $maxAttempts
 * @property int|null          $priority
 * @property float|int|null    $ttl
 *
 * @property []array        $exchanges
 * @property []array        $queues
 * @property []array        $bindings
 *
 * @property []string       $jobNames
 *
 * @property []array        $defaultQueue
 * @property []array        $defaultExchange
 * @property []array        $defaultBind
 *
 * @property int|null          $rpcTimeout
 *
 * @property Serializer|string $serializer
 *
 * @property Debugger          $debugger
 */
class Connection extends Component implements BootstrapInterface
{
    const PROPERTY_ATTEMPT          = 'amqp-attempt';
    const PROPERTY_JOB_NAME         = 'amqp-job-name';
    const PROPERTY_DEBUG_REQUEST_ID = 'amqp-debug-request-id';

    const ENQUEUE_AMQP_LIB   = 'enqueue/amqp-lib';
    const ENQUEUE_AMQP_EXT   = 'enqueue/amqp-ext';
    const ENQUEUE_AMQP_BUNNY = 'enqueue/amqp-bunny';

    const EVENT_BEFORE_SEND     = 'beforeSend';
    const EVENT_AFTER_SEND      = 'afterSend';
    const EVENT_BEFORE_EXECUTE  = 'beforeExecute';
    const EVENT_AFTER_EXECUTE   = 'afterExecute';
    const EVENT_BEFORE_RESPONSE = 'beforeResponse';
    const EVENT_AFTER_RESPONSE  = 'afterResponse';

    /**
     * The connection to the worker could be configured as an array of options
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
     * Keepalive connection
     *
     * @var bool
     */
    public $keepalive = false;

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
     * Defines the amqp interop transport
     *
     * @var string
     */
    public $driver = self::ENQUEUE_AMQP_LIB;

    /**
     * Max attempts to requeue message
     *
     * @var int
     */
    public $maxAttempts = 1;

    /**
     * Default message priority
     *
     * @var int|null
     */
    public $priority = null;

    /**
     * Default message time to live
     *
     * @var float|int|null
     */
    public $ttl = null;

    /**
     * Queue config list
     *
     * @var []array
     */
    public $queues = [];

    /**
     * Exchange config list
     *
     * @var []array
     */
    public $exchanges = [];

    /**
     * Binding config list
     *
     * @var []array
     */
    public $bindings = [];

    /**
     * Named Job
     *
     * @var []string
     */
    public $jobNames = [];

    /**
     * Default Queue config
     *
     * @var []array
     */
    public $defaultQueue = [
        'flags'     => AmqpQueue::FLAG_DURABLE,
        'arguments' => [],
    ];

    /**
     * Default Exchange config
     *
     * @var []array
     */
    public $defaultExchange = [
        'type'      => AmqpTopic::TYPE_DIRECT,
        'flags'     => AmqpTopic::FLAG_DURABLE,
        'arguments' => [],
    ];

    /**
     * Default Bind config
     *
     * @var []array
     */
    public $defaultBind = [
        'routingKey' => null,
        'flags'      => AmqpBind::FLAG_NOPARAM,
        'arguments'  => [],
    ];

    /**
     * The time RPC Job waits for an response. In seconds.
     *
     * @var int|null
     */
    public $rpcTimeout = 30;

    /**
     * @var Serializer
     */
    public $serializer = JsonSerializer::class;

    /**
     * @var array|null
     */
    public $debugger = null;

    /** @var string */
    protected $_debug_request_id        = '';
    /** @var string */
    protected $_debug_request_action    = '';
    /** @var string */
    protected $_debug_parent_message_id = '';

    /** @var AmqpQueue */
    protected $_callbackQueue;
    /** @var AmqpConsumer */
    protected $_callbackConsumer;
    /** @var AmqpProducer */
    protected $_callbackProducer;

    /**
     * @var AmqpContext
     */
    protected $_context;

    /**
     * AmqpQueue list
     *
     * @var []AmqpQueue
     */
    protected $_queues = [];

    /**
     * AmqpTopic list
     *
     * @var []AmqpTopic
     */
    protected $_exchanges = [];

    /**
     * @var Connection
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

        Yii::$app->set('yii-amqp-log', [
            'class' => 'yii\elasticsearch\Connection',
            'autodetectCluster' => false,
            'nodes' => [
                ['http_address' => 'yii-amqp-log.prod.chemexol.ru:80'],
            ],
        ]);

        $this->debugger = [
            'targets' => [
                [
                    'class' => 'matrozov\yii2amqp\debugger\targets\ElasticsearchTarget',
                    'index' => 'yii-amqp',
                    'db'    => 'yii-amqp-log',
                    'extraFields' => [
                        'namespace'    => env('NAMESPACE'),
                        'microservice' => Yii::$app->id,
                        'pod'          => env('HOSTNAME'),
                    ],
                ],
            ],
        ];

        if ($this->debugger) {
            if (is_array($this->debugger) && !isset($this->debugger['class'])) {
                $this->debugger['class'] = Debugger::class;
            }

            $this->debugger = Instance::ensure($this->debugger);

            $this->_debug_request_id     = uniqid('', true);
            $this->_debug_request_action = Yii::$app->requestedAction ? Yii::$app->requestedAction->getUniqueId() : '';

            Yii::$app->on(Application::EVENT_BEFORE_REQUEST, function () {
                $this->_debug_request_id = uniqid('', true);
            });

            Yii::$app->on(Application::EVENT_BEFORE_ACTION, function () {
                $this->_debug_request_action = Yii::$app->requestedAction->getUniqueId();
            });

            if (Yii::$app->request instanceof Request) {
                Yii::$app->on(Application::EVENT_AFTER_ACTION, function () {
                    Yii::$app->response->headers->add('amqp-debug-request-id', $this->_debug_request_id);
                });
            }
        }

        Yii::$app->on(Application::EVENT_AFTER_REQUEST, function () {
            $this->close();

            if ($this->debugger) {
                $this->debugger->shutdown();
            }
        });

        $this->on(static::EVENT_AFTER_EXECUTE, function () {
            if ($this->debugger) {
                $this->debugger->flush();
            }
        });

        self::$_instance = $this;
    }

    /**
     * @param Connection|null $connection
     *
     * @return Connection
     * @throws
     */
    public static function instance(Connection $connection = null): Connection
    {
        if ($connection == null) {
            if (self::$_instance == null) {
                Yii::$app->get('amqp');
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
    protected function getCommandId(): string
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
        if (!($app instanceof Application)) {
            return;
        }

        $app->controllerMap[$this->getCommandId()] = [
            'class'      => Command::class,
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
            return;
        }

        $config = [
            'dsn'   => $this->dsn,
            'host'  => $this->host,
            'port'  => $this->port,
            'user'  => $this->user,
            'pass'  => $this->password,
            'vhost' => $this->vhost,

            'keepalive' => $this->keepalive,

            'read_timeout'       => $this->readTimeout,
            'write_timeout'      => $this->writeTimeout,
            'connection_timeout' => $this->connectionTimeout,

            'heartbeat' => $this->heartbeat,
            'persisted' => $this->persisted,
            'lazy'      => $this->lazy,

            'qos_global'         => $this->qosGlobal,
            'qos_prefetch_size'  => $this->qosPrefetchSize,
            'qos_prefetch_count' => $this->qosPrefetchCount,

            'ssl_on'     => $this->sslOn,
            'ssl_verify' => $this->sslVerify,
            'ssl_cacert' => $this->sslCacert,
            'ssl_cert'   => $this->sslCert,
            'ssl_key'    => $this->sslKey,
        ];

        $config = array_filter($config, function ($value) {
            return null !== $value;
        });

        switch ($this->driver) {
            case self::ENQUEUE_AMQP_LIB:
                {
                    $connectionClass = AmqpLibConnectionFactory::class;
                }
                break;
            case self::ENQUEUE_AMQP_EXT:
                {
                    $connectionClass = AmqpExtConnectionFactory::class;
                }
                break;
            case self::ENQUEUE_AMQP_BUNNY:
                {
                    $connectionClass = AmqpBunnyConnectionFactory::class;
                }
                break;
            default:
            {
                throw new InvalidConfigException('Invalid driver');
            }
        }

        /** @var AmqpConnectionFactory $factory */
        $factory = new $connectionClass($config);

        $this->_context = $factory->createContext();

        if ($this->_context instanceof DelayStrategyAware) {
            $this->_context->setDelayStrategy(new DelayStrategy($this));
        }

        $this->setup();

        $this->_callbackQueue = $this->_context->createQueue(Yii::$app->id . '.callback.' . substr(md5(uniqid('', true)), 0, 8));
        $this->_callbackQueue->addFlag(AmqpQueue::FLAG_IFUNUSED);
        $this->_callbackQueue->addFlag(AmqpQueue::FLAG_EXCLUSIVE);
        $this->_callbackQueue->addFlag(AmqpQueue::FLAG_AUTODELETE);
        $this->_callbackQueue->setArgument('x-message-ttl', $this->rpcTimeout ? $this->rpcTimeout * 1000 * 2 : null);
        $this->_context->declareQueue($this->_callbackQueue);
        $this->_callbackConsumer = $this->_context->createConsumer($this->_callbackQueue);
        $this->_callbackProducer = $this->_context->createProducer();
    }

    /**
     * Close amqp connection
     */
    public function close()
    {
        if (!$this->_context) {
            return;
        }

        if ($this->_callbackQueue) {
            $this->_context->deleteQueue($this->_callbackQueue);

            $this->_callbackQueue    = null;
            $this->_callbackConsumer = null;
            $this->_callbackProducer = null;
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

            foreach (['name', 'flags', 'arguments'] as $field) {
                if (!key_exists($field, $queueConfig)) {
                    throw new InvalidConfigException('Queue config must contain `'.$field.'` field');
                }
            }

            $queue = $this->_context->createQueue($queueConfig['name']);
            $queue->addFlag($queueConfig['flags']);
            $queue->setArguments($queueConfig['arguments']);
            $this->_context->declareQueue($queue);

            $this->_queues[$queueConfig['name']] = $queue;
        }

        foreach ($this->exchanges as $exchangeConfig) {
            $exchangeConfig = ArrayHelper::merge($this->defaultExchange, $exchangeConfig);

            foreach (['name', 'type', 'flags', 'arguments'] as $field) {
                if (!key_exists($field, $exchangeConfig)) {
                    throw new InvalidConfigException('Exchange config must contain `'.$field.'` field');
                }
            }

            $exchange = $this->_context->createTopic($exchangeConfig['name']);
            $exchange->setType($exchangeConfig['type']);
            $exchange->addFlag($exchangeConfig['flags']);
            $exchange->setArguments($exchangeConfig['arguments']);
            $this->_context->declareTopic($exchange);

            $this->_exchanges[$exchangeConfig['name']] = $exchange;
        }

        foreach ($this->bindings as $bindConfig) {
            $bindConfig = ArrayHelper::merge($this->defaultBind, $bindConfig);

            foreach (['queue', 'exchange', 'routingKey', 'flags', 'arguments'] as $field) {
                if (!key_exists($field, $bindConfig)) {
                    throw new InvalidConfigException('Bind config must contain `'.$field.'` field');
                }
            }

            if (!isset($this->_queues[$bindConfig['queue']])) {
                throw new ErrorException('Can\'t bind unknown Queue!');
            }

            if (!isset($this->_exchanges[$bindConfig['exchange']])) {
                throw new ErrorException('Can\'t bind unknown Exchange!');
            }

            $this->_context->bind(new AmqpBind($this->_queues[$bindConfig['queue']], $this->_exchanges[$bindConfig['exchange']], $bindConfig['routingKey'], $bindConfig['flags'], $bindConfig['arguments']));
        }
    }

    /**
     * @return AmqpContext
     * @throws InvalidConfigException
     */
    public function getContext(): AmqpContext
    {
        $this->open();

        return $this->_context;
    }

    /**
     * @param string $exchangeName
     *
     * @return AmqpTopic
     * @throws ErrorException
     */
    public function getExchange(string $exchangeName): AmqpTopic
    {
        if (!isset($this->_exchanges[$exchangeName])) {
            throw new ErrorException('Exchange with name `'.$exchangeName.'` not found!');
        }

        return $this->_exchanges[$exchangeName];
    }

    /**
     * @param string $queueName
     *
     * @return AmqpQueue
     * @throws ErrorException
     */
    public function getQueue(string $queueName): AmqpQueue
    {
        if (!isset($this->_queues[$queueName])) {
            throw new ErrorException('Queue with name `'.$queueName.'` not found!');
        }

        return $this->_queues[$queueName];
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

        $message->setMessageId(uniqid('', true));
        $message->setTimestamp(time());

        $message->setProperty(self::PROPERTY_ATTEMPT, 1);

        $jobName = array_search(get_class($job), $this->jobNames);

        if ($job instanceof RequestNamedJob) {
            $jobName = $job::jobName();
        }

        if ($jobName) {
            $message->setProperty(self::PROPERTY_JOB_NAME, $jobName);
        }

        $message->setBody($this->serializer->serialize($job));

        return $message;
    }

    /**
     * @param AmqpDestination $target
     * @param RpcRequestJob   $job
     *
     * @return RpcResponseJob|bool|null
     * @throws
     */
    protected function sendRpcMessage(AmqpDestination $target, RpcRequestJob $job)
    {
        $message = $this->createMessage($job);

        $message->setReplyTo($this->_callbackQueue->getQueueName());
        $message->setCorrelationId(uniqid('', true));

        $producer = $this->_context->createProducer();

        $this->prepareMessage($producer, $message, $job);

        $pair_id = false;

        try {
            $this->beforeSend($target, $job, $message);

            $pair_id = $this->debugSendStart($target, $message, 'rpc', [
                'job' => get_class($job),
            ]);

            $producer->send($target, $message);

            $this->afterSend($target, $job, $message);

            $result = null;

            if ($this->rpcTimeout === null) {
                $end = null;
            } else {
                $end = microtime(true) + $this->rpcTimeout;
            }

            while (true) {
                if ($end === null) {
                    $responseMessage = $this->_callbackConsumer->receive(0);
                } else {
                    $responseMessage = $this->_callbackConsumer->receive((int)(($end - microtime(true)) * 1000));
                }

                if (!$responseMessage) {
                    throw new RpcTimeoutException('Queue timeout!');
                }

                if ($message->getCorrelationId() != $responseMessage->getCorrelationId()) {
                    $error = new NeedRedeliveryException('Invalid correlation ID');

                    if ($this->redelivery(null, $responseMessage, $this->_callbackConsumer->getQueue(), $error)) {
                        $this->_callbackConsumer->acknowledge($responseMessage);
                    } else {
                        throw new ErrorException('Can\'t redelivery invalid callback message');
                    }

                    if (($end !== null) && (microtime(true) > $end)) {
                        throw new RpcTimeoutException('Queue timeout!');
                    }

                    continue;
                }

                $this->_callbackConsumer->acknowledge($responseMessage);

                $responseJob = $this->serializer->deserialize($responseMessage->getBody());

                if (!($responseJob instanceof RpcResponseJob)) {
                    throw new ErrorException('Root object must be RpcResponseJob!');
                }

                if ($responseJob instanceof RpcFalseResponseJob) {
                    $result = false;
                    break;
                }

                if ($responseJob instanceof RpcExceptionResponseJob) {
                    throw $responseJob->exception();
                }

                $result = $responseJob;
                break;
            }
        } catch (Throwable $exception) {
            if ($pair_id) {
                $this->debugSendEnd($pair_id, $exception);
            }

            throw $exception;
        }

        if ($pair_id) {
            $this->debugSendEnd($pair_id);
        }

        return $result;
    }

    /**
     * @param AmqpDestination $target
     * @param BaseJob         $job
     *
     * @return bool
     * @throws
     */
    protected function sendSimpleMessage(AmqpDestination $target, BaseJob $job)
    {
        $message = $this->createMessage($job);

        $producer = $this->_context->createProducer();

        $this->prepareMessage($producer, $message, $job);

        $pair_id = false;

        try {
            $this->beforeSend($target, $job, $message);

            $pair_id = $this->debugSendStart($target, $message, 'simple');

            $producer->send($target, $message);

            $this->afterSend($target, $job, $message);
        }
        catch (Throwable $exception) {
            if ($pair_id) {
                $this->debugSendEnd($pair_id, $exception);
            }

            throw $exception;
        }

        if ($pair_id) {
            $this->debugSendEnd($pair_id);
        }

        return true;
    }

    /**
     * @param RequestJob  $job
     * @param string|null $exchangeName
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

        $exchange = $this->getExchange($exchangeName);

        if ($job instanceof RpcRequestJob) {
            $result = $this->sendRpcMessage($exchange, $job);
        }
        else {
            $result = $this->sendSimpleMessage($exchange, $job);
        }

        return $result;
    }

    /**
     * @param RpcRequestJob[] $jobs
     * @param string[]        $exchangeNames
     * @param int|null|false  $rpcTimeout
     * @return RpcSendBatchAsync
     * @throws DeliveryDelayNotSupportedException
     * @throws ErrorException
     * @throws Exception
     * @throws Exception\InvalidDestinationException
     * @throws Exception\InvalidMessageException
     * @throws InvalidConfigException
     */
    public function sendBatchAsync(array $jobs, array $exchangeNames = [], $rpcTimeout = false): RpcSendBatchAsync
    {
        $this->open();

        $producer = $this->_context->createProducer();

        $linked = [];

        foreach ($jobs as $idx => $job) {
            if (!($job instanceof RpcRequestJob)) {
                throw new InvalidArgumentException('Only RpcRequestJob allowed for sendBatch');
            }

            $message = $this->createMessage($job);

            $correlationId = uniqid('', true);

            $message->setReplyTo($this->_callbackQueue->getQueueName());
            $message->setCorrelationId($correlationId);

            $this->prepareMessage($producer, $message, $job, $rpcTimeout);

            if (!empty($exchangeNames[$idx])) {
                $exchangeName = $exchangeNames[$idx];
            } else {
                $exchangeName = $job::exchangeName();
            }

            $exchange = $this->getExchange($exchangeName);

            $this->beforeSend($exchange, $job, $message);

            $pair_id = $this->debugSendStart($exchange, $message, 'rpc', [
                'job' => get_class($job),
            ]);

            $linked[$correlationId] = [
                'idx'     => $idx,
                'job'     => $job,
                'message' => $message,
                'pair_id' => $pair_id,
            ];

            $producer->send($exchange, $message);

            $this->afterSend($exchange, $job, $message);
        }

        return new RpcSendBatchAsync($this, $this->_callbackConsumer, $linked, $rpcTimeout);
    }

    /**
     * @param RpcRequestJob[] $jobs
     * @return array
     * @throws DeliveryDelayNotSupportedException
     * @throws ErrorException
     * @throws Exception
     * @throws Exception\InvalidDestinationException
     * @throws Exception\InvalidMessageException
     * @throws HttpException
     * @throws InvalidConfigException
     * @throws RpcTimeoutException
     * @throws Throwable
     */
    public function sendBatch($jobs)
    {
        $batch = $this->sendBatchAsync($jobs);

        while (!$batch->isReady()) {
            sleep(1);
        }

        return $batch->result();
    }

    /**
     * @param AmqpMessage $message
     * @param RpcResponseJob $responseJob
     *
     * @return AmqpMessage
     * @throws DeliveryDelayNotSupportedException
     * @throws Throwable
     */
    protected function replyRpcMessage(AmqpMessage $message, RpcResponseJob $responseJob): AmqpMessage
    {
        $queueName = $message->getReplyTo();

        $queue = $this->_context->createQueue($queueName);

        $responseMessage = $this->createMessage($responseJob);
        $responseMessage->setCorrelationId($message->getCorrelationId());

        $producer = $this->_context->createProducer();

        $rpcTimeout = $message->getExpiration();
        $rpcTimeout = ($rpcTimeout !== null) ? ($rpcTimeout / 1000) : false;

        $this->prepareMessage($producer, $responseMessage, $responseJob, $rpcTimeout);

        $pair_id = false;

        try {
            $this->beforeSend($queue, $responseJob, $responseMessage);

            $pair_id = $this->debugSendStart($queue, $responseMessage, 'reply', [
                'reply_to' => $message->getMessageId(),
            ]);

            $producer->send($queue, $responseMessage);

            $this->afterSend($queue, $responseJob, $responseMessage);
        }
        catch (Throwable $exception) {
            if ($pair_id) {
                $this->debugSendEnd($pair_id, $exception);
            }

            throw $exception;
        }

        if ($pair_id) {
            $this->debugSendEnd($pair_id);
        }

        return $responseMessage;
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
        $responseMessage = null;
        $throw           = null;

        try {
            $this->beforeExecute($job, $message, $consumer);

            if ($job instanceof AccessControlJob) {
                AccessControl::allows($job);
            }

            $responseJob = $job->execute($this, $message);

            if (!($responseJob instanceof RpcResponseJob)) {
                throw new ErrorException('You must return response RpcResponseJob for RpcRequestJob!');
            }

            if (!$responseJob) {
                $responseJob = new RpcFalseResponseJob();
            }

            $this->beforeResponse($job, $message, $responseJob, $consumer);
            $responseMessage = $this->replyRpcMessage($message, $responseJob);
            $this->afterResponse($job, $message, $responseJob, $responseMessage, $consumer);

            $this->afterExecute($job, $message, $responseJob, $responseMessage, $consumer);
        } catch (Throwable $exception) {
            if (!$responseMessage) {
                $throw = $this->handleRpcMessageException($exception, $job, $message, $consumer);
            } else {
                $throw = $exception;
            }
        }

        $consumer->acknowledge($message);

        if ($throw) {
            throw $throw;
        }
    }

    /**
     * @param Throwable     $exception
     * @param RpcExecuteJob $job
     * @param AmqpMessage   $message
     * @param AmqpConsumer  $consumer
     *
     * @return Throwable|null
     * @throws DeliveryDelayNotSupportedException
     * @throws Throwable
     */
    protected function handleRpcMessageException(Throwable $exception, RpcExecuteJob $job, AmqpMessage $message, AmqpConsumer $consumer)
    {
        if (($exception instanceof HttpException) || ($exception instanceof RpcTransferableException)) {
            $responseJob = new RpcExceptionResponseJob($exception);

            $this->replyRpcMessage($message, $responseJob);

            Yii::$app->getErrorHandler()->logException($exception);

            return null;
        }

        if ($exception instanceof NeedRedeliveryException) {
            if ($this->redelivery($job, $message, $consumer->getQueue(), $exception)) {
                Yii::$app->getErrorHandler()->logException(new RedeliveryException($exception->getMessage(), 0, $exception));
            }
            else {
                $responseJob = new RpcFalseResponseJob();

                $this->replyRpcMessage($message, $responseJob);

                Yii::$app->getErrorHandler()->logException($exception);
            }

            return null;
        }

        if (!$this->redelivery($job, $message, $consumer->getQueue(), $exception)) {
            $responseJob = new RpcExceptionResponseJob($exception);

            $this->replyRpcMessage($message, $responseJob);
        }

        return $exception;
    }

    /**
     * @param ExecuteJob   $job
     * @param AmqpMessage  $message
     * @param AmqpConsumer $consumer
     *
     * @throws Throwable
     */
    protected function handleSimpleMessage(ExecuteJob $job, AmqpMessage $message, AmqpConsumer $consumer)
    {
        $throw = null;

        try {
            $this->beforeExecute($job, $message, $consumer);

            $job->execute($this, $message);

            $this->afterExecute($job, $message, null, null, $consumer);
        } catch (Throwable $exception) {
            $throw = $this->handleSimpleMessageException($exception, $job, $message, $consumer);
        }

        $consumer->acknowledge($message);

        if ($throw) {
            throw $throw;
        }
    }

    /**
     * @param Throwable    $exception
     * @param ExecuteJob   $job
     * @param AmqpMessage  $message
     * @param AmqpConsumer $consumer
     *
     * @return Throwable|null
     * @throws DeliveryDelayNotSupportedException
     * @throws Throwable
     */
    protected function handleSimpleMessageException(Throwable $exception, ExecuteJob $job, AmqpMessage $message, AmqpConsumer $consumer)
    {
        if ($exception instanceof HttpException) {
            Yii::$app->getErrorHandler()->logException($exception);

            return null;
        }

        if ($exception instanceof NeedRedeliveryException) {
            if ($this->redelivery($job, $message, $consumer->getQueue(), $exception)) {
                Yii::$app->getErrorHandler()->logException(new RedeliveryException($exception->getMessage(), 0, $exception));
            }
            else {
                Yii::$app->getErrorHandler()->logException($exception);
            }

            return null;
        }

        $this->redelivery($job, $message, $consumer->getQueue(), $exception);

        return $exception;
    }

    /**
     * @param AmqpMessage  $message
     * @param AmqpConsumer $consumer
     *
     * @return ExecuteJob
     * @throws ErrorException
     * @throws Exception
     * @throws Throwable
     */
    public function messageToJob(AmqpMessage $message, AmqpConsumer $consumer)
    {
        $jobClassName = $message->getProperty(self::PROPERTY_JOB_NAME);

        if ($jobClassName !== null) {
            if (array_key_exists($jobClassName, $this->jobNames)) {
                $jobClassName = $this->jobNames[$jobClassName];

                if (!class_exists($jobClassName)) {
                    throw new ErrorException('Named job className not found: '.$jobClassName);
                }
            }
            else {
                $jobClassName = null;
            }
        }

        try {
            $job = $this->serializer->deserialize($message->getBody(), $jobClassName);
        }
        catch (Throwable $exception) {
            $this->redelivery(null, $message, $consumer->getQueue(), $exception);

            $consumer->acknowledge($message);

            throw $exception;
        }

        /* @var ExecuteJob $job */
        if (!($job instanceof ExecuteJob)) {
            $consumer->acknowledge($message);

            if (is_object($job)) {
                throw new ErrorException('Can\'t execute unknown job type: '.get_class($job));
            }
            else {
                throw new ErrorException('Can\'t execute unknown message: '.gettype($job));
            }
        }

        $job->setMessage($message);

        return $job;
    }

    /**
     * @param ExecuteJob   $job
     * @param AmqpMessage  $message
     * @param AmqpConsumer $consumer
     *
     * @throws Throwable
     */
    protected function handleMessage(ExecuteJob $job, AmqpMessage $message, AmqpConsumer $consumer)
    {
        if ($job instanceof RpcExecuteJob) {
            $this->handleRpcMessage($job, $message, $consumer);
        } else {
            $this->handleSimpleMessage($job, $message, $consumer);
        }
    }

    /**
     * @param []string|string|null $queueNames
     * @param int $timeout
     *
     * @throws
     */
    public function listen($queueNames = null, $timeout = 0)
    {
        $this->open();

        if (empty($queueNames)) {
            $queueNames = array_keys($this->_queues);
        } else {
            foreach ((array)$queueNames as $queueName) {
                $this->getQueue($queueName);
            }
        }

        $callback = function (AmqpMessage $message, AmqpConsumer $consumer) {
            $job = $this->messageToJob($message, $consumer);

            $pair_id = $this->debugExecuteStart($consumer, $message, [
                'job' => get_class($job),
            ]);

            try {
                $this->handleMessage($job, $message, $consumer);
            } catch (Throwable $exception) {
                if ($pair_id) {
                    $this->debugExecuteEnd($pair_id, $exception);
                }

                throw $exception;
            }

            if ($pair_id) {
                $this->debugExecuteEnd($pair_id);
            }

            return true;
        };

        $subscriptionConsumer = $this->_context->createSubscriptionConsumer();

        foreach ($queueNames as $queueName) {
            $queue = $this->getQueue($queueName);
            $consumer = $this->_context->createConsumer($queue);

            $subscriptionConsumer->subscribe($consumer, $callback);
        }

        $subscriptionConsumer->consume($timeout);
    }

    /**
     * @param AmqpMessage    $message
     * @param AmqpProducer   $producer
     * @param BaseJob|null   $job
     * @param int|null|false $rpcTimeout
     *
     * @throws DeliveryDelayNotSupportedException
     */
    protected function prepareMessage(AmqpProducer $producer, AmqpMessage $message, $job = null, $rpcTimeout = false)
    {
        if ($message->getDeliveryMode() === null) {
            if ($job instanceof PersistentJob) {
                $message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);
            }
        }

        if ($message->getPriority() === null) {
            if (($job instanceof PriorityJob) && (($priority = $job->getPriority()) !== null)) {
                $message->setPriority($priority);
            } elseif ($this->priority !== null) {
                $message->setPriority($this->priority);
            }
        }

        if ($message->getExpiration() === null) {
            $rpcTimeout = ($rpcTimeout !== false) ? $rpcTimeout : $this->rpcTimeout;

            if (($job instanceof ExpiredJob) && (($ttl = $job->getTtl()) !== null)) {
                $message->setExpiration($ttl * 1000);
            } elseif (($job instanceof RpcRequestJob) && ($rpcTimeout !== null)) {
                $message->setExpiration($rpcTimeout * 1000);
            } elseif ($this->ttl !== null) {
                $message->setExpiration($this->ttl * 1000);
            }
        }

        if (($job instanceof DelayedJob) && (($delay = $job->getDelay()) !== null)) {
            $producer->setDeliveryDelay($delay * 1000);
        }

        if ($this->debugger) {
            $message->setProperty(self::PROPERTY_DEBUG_REQUEST_ID, $this->_debug_request_id);
        }
    }

    /**
     * @param BaseJob|null    $job
     * @param AmqpMessage     $message
     * @param AmqpDestination $target
     * @param Throwable       $error
     *
     * @return bool
     * @throws DeliveryDelayNotSupportedException
     * @throws Throwable
     */
    public function redelivery($job, AmqpMessage $message, AmqpDestination $target, Throwable $error)
    {
        $attempt = $message->getProperty(self::PROPERTY_ATTEMPT, 1);

        if ($job && ($job instanceof RetryableJob)) {
            if (!$job->canRetry($attempt, $error)) {
                return false;
            }
        } elseif (!($error instanceof NeedRedeliveryException) && ($attempt >= $this->maxAttempts)) {
            return false;
        }

        $newMessage = $this->_context->createMessage($message->getBody(), $message->getProperties(), $message->getHeaders());
        $newMessage->setDeliveryMode($message->getDeliveryMode());

        $newMessage->setProperty(self::PROPERTY_ATTEMPT, ++$attempt);

        $producer = $this->_context->createProducer();

        $this->prepareMessage($producer, $newMessage, $job);

        $pair_id = false;

        try {
            $this->beforeSend($target, $job, $newMessage);

            $pair_id = $this->debugSendStart($target, $newMessage, 'redelivery', [
                'redelivery_to' => $message->getMessageId(),
            ]);

            $producer->send($target, $newMessage);

            $this->afterSend($target, $job, $newMessage);
        }
        catch (Throwable $exception) {
            if ($pair_id) {
                $this->debugSendEnd($pair_id, $exception);
            }

            throw $exception;
        }

        if ($pair_id) {
            $this->debugSendEnd($pair_id);
        }

        return true;
    }

    /**
     * @param AmqpDestination $target
     * @param BaseJob|null    $requestJob
     * @param AmqpMessage     $requestMessage
     */
    public function beforeSend(AmqpDestination $target, ?BaseJob $requestJob, AmqpMessage $requestMessage)
    {
        $event = new SendEvent([
            'target'         => $target,
            'requestJob'     => $requestJob,
            'requestMessage' => $requestMessage,
        ]);

        $this->trigger(static::EVENT_BEFORE_SEND, $event);

        if ($requestJob instanceof Component) {
            $event = new JobEvent([
                'job' => $requestJob,
            ]);

            /* @var Component $requestJob */
            $requestJob->trigger(static::EVENT_BEFORE_SEND, $event);
        }
    }

    /**
     * @param AmqpDestination $target
     * @param BaseJob|null    $requestJob
     * @param AmqpMessage     $requestMessage
     */
    public function afterSend(AmqpDestination $target, ?BaseJob $requestJob, AmqpMessage $requestMessage)
    {
        $event = new SendEvent([
            'target'         => $target,
            'requestJob'     => $requestJob,
            'requestMessage' => $requestMessage,
        ]);

        $this->trigger(static::EVENT_AFTER_SEND, $event);

        if ($requestJob instanceof Component) {
            $event = new JobEvent([
                'job' => $requestJob,
            ]);

            /* @var Component $requestJob */
            $requestJob->trigger(static::EVENT_AFTER_SEND, $event);
        }
    }

    /**
     * @param ExecuteJob   $requestJob
     * @param AmqpMessage  $requestMessage
     * @param AmqpConsumer $consumer
     */
    public function beforeExecute(ExecuteJob $requestJob, AmqpMessage $requestMessage, AmqpConsumer $consumer)
    {
        $event = new ExecuteEvent([
            'requestJob'      => $requestJob,
            'requestMessage'  => $requestMessage,
            'consumer'        => $consumer,
        ]);

        $this->trigger(static::EVENT_BEFORE_EXECUTE, $event);

        if ($requestJob instanceof Component) {
            $event = new JobEvent([
                'job' => $requestJob,
            ]);

            /* @var Component $requestJob */
            $requestJob->trigger(static::EVENT_BEFORE_EXECUTE, $event);
        }
    }

    /**
     * @param ExecuteJob          $requestJob
     * @param AmqpMessage         $requestMessage
     * @param RpcResponseJob|null $responseJob
     * @param AmqpMessage|null    $responseMessage
     * @param AmqpConsumer        $consumer
     */
    public function afterExecute(ExecuteJob $requestJob, AmqpMessage $requestMessage, ?RpcResponseJob $responseJob, ?AmqpMessage $responseMessage, AmqpConsumer $consumer)
    {
        $event = new ExecuteEvent([
            'requestJob'      => $requestJob,
            'requestMessage'  => $requestMessage,
            'responseJob'     => $responseJob,
            'responseMessage' => $responseMessage,
            'consumer'        => $consumer,
        ]);

        $this->trigger(static::EVENT_AFTER_EXECUTE, $event);

        if ($requestJob instanceof Component) {
            $event = new JobEvent([
                'job' => $requestJob,
            ]);

            /* @var Component $requestJob */
            $requestJob->trigger(static::EVENT_AFTER_EXECUTE, $event);
        }
    }

    /**
     * @param ExecuteJob     $requestJob
     * @param AmqpMessage    $requestMessage
     * @param RpcResponseJob $responseJob
     * @param AmqpConsumer   $consumer
     */
    public function beforeResponse(ExecuteJob $requestJob, AmqpMessage $requestMessage, RpcResponseJob $responseJob, AmqpConsumer $consumer)
    {
        $event = new ResponseEvent([
            'requestJob'      => $requestJob,
            'requestMessage'  => $requestMessage,
            'consumer'        => $consumer,
        ]);

        $this->trigger(static::EVENT_BEFORE_RESPONSE, $event);

        if ($requestJob instanceof Component) {
            $event = new JobEvent([
                'job' => $requestJob,
            ]);

            /* @var Component $requestJob */
            $requestJob->trigger(static::EVENT_BEFORE_RESPONSE, $event);
        }
    }

    /**
     * @param ExecuteJob          $requestJob
     * @param AmqpMessage         $requestMessage
     * @param RpcResponseJob|null $responseJob
     * @param AmqpMessage|null    $responseMessage
     * @param AmqpConsumer        $consumer
     */
    public function afterResponse(ExecuteJob $requestJob, AmqpMessage $requestMessage, RpcResponseJob $responseJob, AmqpMessage $responseMessage, AmqpConsumer $consumer)
    {
        $event = new ResponseEvent([
            'requestJob'      => $requestJob,
            'requestMessage'  => $requestMessage,
            'responseJob'     => $responseJob,
            'responseMessage' => $responseMessage,
            'consumer'        => $consumer,
        ]);

        $this->trigger(static::EVENT_AFTER_RESPONSE, $event);

        if ($requestJob instanceof Component) {
            $event = new JobEvent([
                'job' => $requestJob,
            ]);

            /* @var Component $requestJob */
            $requestJob->trigger(static::EVENT_AFTER_RESPONSE, $event);
        }
    }

    /**
     * @return int
     */
    protected static function debugTime(): int
    {
        return (int)round(microtime(true) * 1000);
    }

    /**
     * @param AmqpConsumer $consumer
     * @param AmqpMessage  $message
     * @param array        $fields
     * @return bool|string
     */
    protected function debugExecuteStart(AmqpConsumer $consumer, AmqpMessage $message, array $fields = [])
    {
        if (!$this->debugger) {
            return false;
        }

        $this->_debug_request_id        = $message->getProperty(self::PROPERTY_DEBUG_REQUEST_ID);
        $this->_debug_parent_message_id = $message->getMessageId();

        $pair_id = uniqid('', true);

        $debug = [
            'app_id'     => Yii::$app->id,
            'time'       => self::debugTime(),
            'request_id' => $this->_debug_request_id,
            'message_id' => $message->getMessageId(),
            'queue'      => $consumer->getQueue()->getQueueName(),
        ];

        $debug = ArrayHelper::merge($debug, $fields);

        $this->debugger->logStart('execute', $pair_id, $debug);

        return $pair_id;
    }

    /**
     * @param string         $pair_id
     * @param Throwable|null $exception
     */
    protected function debugExecuteEnd(string $pair_id, Throwable $exception = null)
    {
        if (!$this->debugger) {
            return;
        }

        $debug = [
            'time' => self::debugTime(),
        ];

        if ($exception) {
            $debug['exception'] = [
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'trace'   => $exception->getTraceAsString(),
            ];
        }

        $this->debugger->logEnd('execute', $pair_id, $debug);

        $this->_debug_parent_message_id = '';
    }

    /**
     * @param AmqpDestination $destination
     * @param AmqpMessage     $message
     * @param string          $sub_type
     * @param array           $fields
     *
     * @return bool|string
     * @throws ErrorException
     */
    protected function debugSendStart(AmqpDestination $destination, AmqpMessage $message, string $sub_type, array $fields = [])
    {
        if (!$this->debugger) {
            return false;
        }

        if ($destination instanceof AmqpTopic) {
            $target_type = 'topic';
            $target = $destination->getTopicName();
        }
        elseif ($destination instanceof AmqpQueue) {
            $target_type = 'queue';
            $target = $destination->getQueueName();
        }
        else {
            throw new ErrorException('Unknown destination type');
        }

        $pair_id = uniqid('', true);

        $debug = [
            'app_id'         => Yii::$app->id,
            'time'           => self::debugTime(),
            'request_id'     => $this->_debug_request_id,
            'request_action' => empty($this->_debug_parent_message_id) ? $this->_debug_request_action : '',
            'parent_id'      => $this->_debug_parent_message_id,
            'message_id'     => $message->getMessageId(),
            'sub_type'       => $sub_type,
            'target_type'    => $target_type,
            'target'         => $target,
            'message'        => [
                'headers'    => $message->getHeaders(),
                'properties' => $message->getProperties(),
                'body'       => mb_substr($message->getBody(), 0, 4096),
            ],
        ];

        $debug = ArrayHelper::merge($debug, $fields);

        $this->debugger->logStart('send', $pair_id, $debug);

        return $pair_id;
    }

    /**
     * @param string         $pair_id
     * @param Throwable|null $exception
     * @param array          $fields
     *
     */
    protected function debugSendEnd(string $pair_id, Throwable $exception = null, array $fields = [])
    {
        if (!$this->debugger) {
            return;
        }

        $debug = [
            'time' => self::debugTime(),
        ];

        if ($exception) {
            $debug['exception'] = [
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'trace'   => $exception->getTraceAsString(),
            ];
        }

        $debug = ArrayHelper::merge($debug, $fields);

        $this->debugger->logEnd('send', $pair_id, $debug);
    }

    public function debugFlush()
    {
        if (!$this->debugger) {
            return;
        }

        $this->debugger->flush();
    }
}
