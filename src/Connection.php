<?php
declare(strict_types=1);

namespace matrozov\yii2amqp;

use Enqueue\AmqpBunny\AmqpConnectionFactory as AmqpBunnyConnectionFactory;
use Enqueue\AmqpExt\AmqpConnectionFactory as AmqpExtConnectionFactory;
use Enqueue\AmqpLib\AmqpConnectionFactory as AmqpLibConnectionFactory;
use Enqueue\AmqpTools\DelayStrategyAware;
use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpConnectionFactory;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use matrozov\yii2amqp\debugger\Debugger;
use matrozov\yii2amqp\events\ExecuteEvent;
use matrozov\yii2amqp\events\JobEvent;
use matrozov\yii2amqp\events\SendEvent;
use matrozov\yii2amqp\exceptions\NeedRedeliveryException;
use matrozov\yii2amqp\exceptions\RedeliveryException;
use matrozov\yii2amqp\exceptions\RpcTimeoutException;
use matrozov\yii2amqp\exceptions\RpcTransferableException;
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
use matrozov\yii2amqp\jobs\simple\ExecuteJob;
use matrozov\yii2amqp\jobs\simple\RequestJob;
use matrozov\yii2amqp\serializers\JsonSerializer;
use matrozov\yii2amqp\serializers\Serializer;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\console\Application;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\web\HttpException;

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
 * @property bool           $keepalive
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
 * @property string         $driver
 *
 * @property int            $maxAttempts
 * @property int|null       $priority
 * @property float|int|null $ttl
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
 * @property int|null       $rpcTimeout
 *
 * @property Serializer     $serializer
 *
 * @property Debugger       $debugger
 */
class Connection extends Component implements BootstrapInterface
{
    const PROPERTY_ATTEMPT          = 'amqp-attempt';
    const PROPERTY_JOB_NAME         = 'amqp-job-name';
    const PROPERTY_DEBUG_REQUEST_ID = 'amqp-debug-request-id';

    const ENQUEUE_AMQP_LIB   = 'enqueue/amqp-lib';
    const ENQUEUE_AMQP_EXT   = 'enqueue/amqp-ext';
    const ENQUEUE_AMQP_BUNNY = 'enqueue/amqp-bunny';

    const EVENT_BEFORE_SEND    = 'beforeSend';
    const EVENT_AFTER_SEND     = 'afterSend';
    const EVENT_BEFORE_EXECUTE = 'beforeExecute';
    const EVENT_AFTER_EXECUTE  = 'afterExecute';


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

    /**
     * @var string      $_debug_request_id
     * @var string      $_debug_request_action
     * @var string|null $_debug_parent_message_id
     */
    protected $_debug_request_id;
    protected $_debug_request_action;
    protected $_debug_parent_message_id;

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

        if ($this->debugger) {
            if (is_array($this->debugger) && !isset($this->debugger['class'])) {
                $this->debugger['class'] = Debugger::class;
            }

            $this->debugger = Instance::ensure($this->debugger);

            $this->_debug_request_id     = uniqid('', true);
            $this->_debug_request_action = Yii::$app->requestedAction ? Yii::$app->requestedAction->getUniqueId() : '';

            Yii::$app->on(Application::EVENT_BEFORE_REQUEST, function() {
                $this->_debug_request_id = uniqid('', true);
            });

            Yii::$app->on(Application::EVENT_BEFORE_ACTION, function() {
                $this->_debug_request_action = Yii::$app->requestedAction->getUniqueId();
            });
        }

        Yii::$app->on(Application::EVENT_AFTER_REQUEST, function () {
            $this->close();

            $this->debugFlush();
        });

        $this->on(static::EVENT_AFTER_EXECUTE, function () {
            $this->debugFlush();
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
        if (!($app instanceof Application)) {
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
            return;
        }

        $config = [
            'dsn'                => $this->dsn,
            'host'               => $this->host,
            'port'               => $this->port,
            'user'               => $this->user,
            'pass'               => $this->password,
            'vhost'              => $this->vhost,

            'keepalive'          => $this->keepalive,

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

        switch ($this->driver) {
            case self::ENQUEUE_AMQP_LIB: {
                $connectionClass = AmqpLibConnectionFactory::class;
            } break;
            case self::ENQUEUE_AMQP_EXT: {
                $connectionClass = AmqpExtConnectionFactory::class;
            } break;
            case self::ENQUEUE_AMQP_BUNNY: {
                $connectionClass = AmqpBunnyConnectionFactory::class;
            } break;
            default: {
                throw new InvalidConfigException('Invalid driver');
            }
        }

        /** @var AmqpConnectionFactory $factory */
        $factory = new $connectionClass($config);

        $this->_context = $factory->createContext();

        if ($this->_context instanceof DelayStrategyAware) {
            $this->_context->setDelayStrategy(new DelayStrategy());
        }

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
     * @throws InvalidConfigException
     */
    public function reopen()
    {
        $this->close();
        $this->open();
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
                    throw new InvalidConfigException('Queue config must contain `' . $field . '` field');
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
                    throw new InvalidConfigException('Exchange config must contain `' . $field . '` field');
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

        if (!empty($this->serializer->contentType())) {
            $message->setContentType($this->serializer->contentType());
        }

        return $message;
    }

    /**
     * @param AmqpDestination $target
     * @param RpcRequestJob  $job
     *
     * @return RpcResponseJob|bool|null
     * @throws
     */
    protected function sendRpcMessage(AmqpDestination $target, RpcRequestJob $job)
    {
        $message = $this->createMessage($job);

        if ($target instanceof AmqpTopic) {
            $exchangeName = $target->getTopicName();
        }
        elseif ($target instanceof AmqpQueue) {
            $exchangeName = $target->getQueueName();
        }
        else {
            $exchangeName = $job::exchangeName();
        }

        $queue = $this->_context->createQueue($exchangeName . '.rpc.callback' . ($this->rpcTimeout ? '.' . ($this->rpcTimeout * 2 * 1000) : ''));
        $queue->addFlag(AmqpDestination::FLAG_IFUNUSED);
        $queue->addFlag(AmqpDestination::FLAG_AUTODELETE);
        $queue->addFlag(AmqpDestination::FLAG_DURABLE);
        $queue->setArgument('x-message-ttl', $this->rpcTimeout ? $this->rpcTimeout * 1000 * 2 : null);
        $this->_context->declareQueue($queue);

        $message->setReplyTo($queue->getQueueName());
        $message->setCorrelationId(uniqid('', true));

        $this->sendMessage($target, $job, $message);

        $consumer = $this->_context->createConsumer($queue);

        $timeout = $this->rpcTimeout;

        $debug = [
            'app_id'     => Yii::$app->id,
            'time'       => microtime(true),
            'request_id' => $this->_debug_request_id,
            'message_id' => $message->getMessageId(),
        ];

        $result = null;

        try {
            while (true) {
                $start = microtime(true);

                $responseMessage = $consumer->receive((int)$timeout * 1000);

                if (!$responseMessage) {
                    throw new RpcTimeoutException('Queue timeout!');
                }

                if ($message->getCorrelationId() != $responseMessage->getCorrelationId()) {
                    $consumer->reject($responseMessage, true);

                    if ($timeout !== null) {
                        $timeout -= (microtime(true) - $start);

                        if ($timeout < 0) {
                            throw new RpcTimeoutException('Queue timeout!');
                        }
                    }

                    continue;
                }

                $consumer->acknowledge($responseMessage);

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
        }
        catch (\Exception $exception) {
            if ($this->debugger) {
                $debug['result']    = $result !== false;
                $debug['exception'] = $exception->getMessage();

                $this->debug('send_end', $debug);
            }

            throw $exception;
        }

        if ($this->debugger) {
            $debug['result'] = $result !== false;

            $this->debug('send_end', $debug);
        }

        return $result;
    }

    /**
     * @param AmqpDestination $target
     * @param BaseJob        $job
     *
     * @return bool
     * @throws
     */
    protected function sendSimpleMessage(AmqpDestination $target, BaseJob $job)
    {
        $message = $this->createMessage($job);

        $debug = [
            'app_id'     => Yii::$app->id,
            'time'       => microtime(true),
            'request_id' => $this->_debug_request_id,
            'message_id' => $message->getMessageId(),
        ];

        try {
            $this->sendMessage($target, $job, $message);
        }
        catch (\Exception $exception) {
            if ($this->debugger) {
                $debug['exception'] = $exception->getMessage();

                $this->debug('send_end', $debug);
            }

            throw $exception;
        }

        if ($this->debugger) {
            $this->debug('send_end', $debug);
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

        if (!isset($this->_exchanges[$exchangeName])) {
            throw new ErrorException('Exchange with name `' . $exchangeName . '` not found!');
        }

        $exchange = $this->_exchanges[$exchangeName];

        if ($job instanceof RpcRequestJob) {
            $result = $this->sendRpcMessage($exchange, $job);
        }
        else {
            $result = $this->sendSimpleMessage($exchange, $job);
        }

        return $result;
    }

    /**
     * @param AmqpMessage    $message
     * @param RpcResponseJob $responseJob
     *
     * @return bool
     * @throws ErrorException
     */
    protected function replyRpcMessage(AmqpMessage $message, RpcResponseJob $responseJob)
    {
        $queueName = $message->getReplyTo();

        $queue = $this->_context->createQueue($queueName);

        $responseMessage = $this->createMessage($responseJob);
        $responseMessage->setCorrelationId($message->getCorrelationId());

        $this->sendMessage($queue, $responseJob, $responseMessage);

        return true;
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
        $exceptionInt = null;
        $exceptionExt = null;

        try {
            $this->beforeExecute($job, null, $message, $consumer);

            try {
                $responseJob = $job->execute($this, $message);

                if (!($responseJob instanceof RpcResponseJob)) {
                    throw new ErrorException('You must return response RpcResponseJob for RpcRequestJob!');
                }

                if (!$responseJob) {
                    $responseJob = new RpcFalseResponseJob();
                }

                $this->replyRpcMessage($message, $responseJob);
            }
            catch (\Exception $exception) {
                $responseJob = null;

                $exceptionInt = $this->handleRpcMessageException($exception, $job, $message, $consumer);
            }

            $this->afterExecute($job, $responseJob, $message, $consumer);
        }
        catch (\Exception $exception) {
            if (!$exceptionInt) {
                $exceptionExt = $this->handleRpcMessageException($exception, $job, $message, $consumer);
            }
        }

        $consumer->acknowledge($message);

        if ($exceptionInt || $exceptionExt) {
            throw $exceptionInt ? $exceptionInt : $exceptionExt;
        }
    }

    /**
     * @param \Exception    $exception
     * @param RpcExecuteJob $job
     * @param AmqpMessage   $message
     * @param AmqpConsumer  $consumer
     *
     * @return \Exception|null
     * @throws \Exception
     */
    protected function handleRpcMessageException(\Exception $exception, RpcExecuteJob $job, AmqpMessage $message, AmqpConsumer $consumer)
    {
        if (($exception instanceof HttpException) || ($exception instanceof RpcTransferableException)) {
            $responseJob = new RpcExceptionResponseJob($exception);

            $this->replyRpcMessage($message, $responseJob);

            Yii::warning($exception->getMessage());

            return null;
        }

        if ($exception instanceof NeedRedeliveryException) {
            if ($this->redelivery($job, $message, $consumer, $exception)) {
                Yii::$app->getErrorHandler()->logException(new RedeliveryException($exception->getMessage(), 0, $exception));
            }
            else {
                $responseJob = new RpcFalseResponseJob();

                $this->replyRpcMessage($message, $responseJob);

                Yii::$app->getErrorHandler()->logException($exception);
            }

            return null;
        }

        if (!$this->redelivery($job, $message, $consumer, $exception)) {
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
     * @throws
     */
    protected function handleSimpleMessage(ExecuteJob $job, AmqpMessage $message, AmqpConsumer $consumer)
    {
        $exceptionInt = null;
        $exceptionExt = null;

        try {
            $this->beforeExecute($job, null, $message, $consumer);

            try {
                $job->execute($this, $message);
            }
            catch (\Exception $exception) {
                $exceptionInt = $this->handleSimpleMessageException($exception, $job, $message, $consumer);
            }

            $this->afterExecute($job, null, $message, $consumer);
        }
        catch (\Exception $exception) {
            if (!$exceptionInt) {
                $exceptionExt = $this->handleSimpleMessageException($exception, $job, $message, $consumer);
            }
        }

        $consumer->acknowledge($message);

        if ($exceptionInt || $exceptionExt) {
            throw $exceptionInt ? $exceptionInt : $exceptionExt;
        }
    }

    /**
     * @param \Exception   $exception
     * @param ExecuteJob   $job
     * @param AmqpMessage  $message
     * @param AmqpConsumer $consumer
     *
     * @return \Exception|null
     * @throws \Exception
     */
    protected function handleSimpleMessageException(\Exception $exception, ExecuteJob $job, AmqpMessage $message, AmqpConsumer $consumer)
    {
        if ($exception instanceof HttpException) {
            Yii::warning($exception->getMessage());

            return null;
        }

        if ($exception instanceof NeedRedeliveryException) {
            if ($this->redelivery($job, $message, $consumer, $exception)) {
                Yii::$app->getErrorHandler()->logException(new RedeliveryException($exception->getMessage(), 0, $exception));
            }
            else {
                Yii::$app->getErrorHandler()->logException($exception);
            }

            return null;
        }

        $this->redelivery($job, $message, $consumer, $exception);

        return $exception;
    }

    /**
     * @param AmqpMessage  $message
     * @param AmqpConsumer $consumer
     *
     * @throws
     */
    protected function handleMessage(AmqpMessage $message, AmqpConsumer $consumer)
    {
        $jobClassName = $message->getProperty(self::PROPERTY_JOB_NAME);

        if ($jobClassName !== null) {
            if (array_key_exists($jobClassName, $this->jobNames)) {
                $jobClassName = $this->jobNames[$jobClassName];

                if (!class_exists($jobClassName)) {
                    throw new ErrorException('Named job className not found: ' . $jobClassName);
                }
            }
            else {
                $jobClassName = null;
            }
        }

        try {
            $job = $this->serializer->deserialize($message->getBody(), $jobClassName);
        }
        catch (\Exception $exception) {
            $this->redelivery(null, $message, $consumer, $exception);

            $consumer->acknowledge($message);

            throw $exception;
        }

        /* @var ExecuteJob $job */
        if (!($job instanceof ExecuteJob)) {
            $consumer->acknowledge($message);

            if (is_object($job)) {
                throw new ErrorException('Can\'t execute unknown job type: ' . get_class($job));
            }
            else {
                throw new ErrorException('Can\'t execute unknown message: ' . gettype($job));
            }
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
    public function listen($queueNames = null, $timeout = null)
    {
        $this->open();

        if (empty($queueNames)) {
            $queueNames = array_keys($this->_queues);
        }

        $subscriptionConsumer = $this->_context->createSubscriptionConsumer();

        foreach ((array)$queueNames as $queueName) {
            if (!isset($this->_queues[$queueName])) {
                throw new ErrorException('Queue config `' . $queueName . '` not found!');
            }

            $consumer = $this->_context->createConsumer($this->_queues[$queueName]);

            $subscriptionConsumer->subscribe($consumer, function(AmqpMessage $message, AmqpConsumer $consumer) {
                $request_id = $this->_debug_request_id;

                $this->_debug_request_id        = $message->getProperty(self::PROPERTY_DEBUG_REQUEST_ID, $request_id);
                $this->_debug_parent_message_id = $message->getMessageId();

                if ($this->debugger) {
                    $debug = [
                        'app_id'     => Yii::$app->id,
                        'time'       => microtime(true),
                        'request_id' => $this->_debug_request_id,
                        'message_id' => $message->getMessageId(),
                        'attempt'    => $message->getProperty(self::PROPERTY_ATTEMPT),
                    ];

                    $this->debug('execute_start', $debug);
                }

                $debug = [
                    'app_id'     => Yii::$app->id,
                    'time'       => microtime(true),
                    'request_id' => $this->_debug_request_id,
                    'message_id' => $message->getMessageId(),
                ];

                try {
                    $this->handleMessage($message, $consumer);
                }
                catch (\Exception $exception) {
                    if ($this->debugger) {
                        $debug['exception'] = $exception->getMessage();

                        $this->debug('execute_end', $debug);
                    }

                    throw $exception;
                }

                if ($this->debugger) {
                    $this->debug('execute_end', $debug);
                }

                $this->_debug_request_id        = $request_id;
                $this->_debug_parent_message_id = null;

                return true;
            });
        }

        while (true) {
            $start = microtime(true);

            $loopTimeout = max(5, (int)$timeout);

            $subscriptionConsumer->consume($loopTimeout * 1000);

            if ($timeout !== null) {
                $timeout -= microtime(true) - $start;
            }

            if ((($timeout !== null) && ($timeout < 0)) || ExitSignal::isExit()) {
                break;
            }
        }
    }

    /**
     * @param AmqpDestination $target
     * @param BaseJob|null    $job
     * @param AmqpMessage     $message
     *
     * @throws
     */
    protected function sendMessage(AmqpDestination $target, $job, AmqpMessage $message)
    {
        $producer = $this->_context->createProducer();

        if ($message->getDeliveryMode() === null) {
            if ($job instanceof PersistentJob) {
                $message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);
            }
        }

        if ($message->getPriority() === null) {
            if (($job instanceof PriorityJob) && (($priority = $job->getPriority()) !== null)) {
                $message->setPriority($priority);
            }
            elseif ($this->priority !== null) {
                $message->setPriority($this->priority);
            }
        }

        if ($message->getExpiration() === null) {
            if (($job instanceof ExpiredJob) && (($ttl = $job->getTtl()) !== null)) {
                $message->setExpiration($ttl * 1000);
            }
            elseif (($job instanceof RpcRequestJob) && ($this->rpcTimeout !== null)) {
                $message->setExpiration($this->rpcTimeout * 1000);
            }
            elseif ($this->ttl !== null) {
                $message->setExpiration($this->ttl * 1000);
            }
        }

        if (($job instanceof DelayedJob) && (($delay = $job->getDelay()) !== null)) {
            $producer->setDeliveryDelay($delay * 1000);
        }

        $message->setProperty(self::PROPERTY_DEBUG_REQUEST_ID, $this->_debug_request_id);

        $this->beforeSend($target, $job, $message);

        $try = 1;

        while (true) {
            try {
                $producer->send($target, $message);
            }
            catch (\Exception $e) {
                Yii::$app->getErrorHandler()->logException(new ErrorException('Send error: "' . $e->getMessage() . '", try: ' . $try . '/3', 0, 1, __FILE__, __LINE__, $e));

                $try++;

                if ($try > 3) {
                    throw new ErrorException('Can\'t send message to queue. Connection closed!');
                }

                $this->reopen();

                continue;
            }

            break;
        }

        $this->afterSend($target, $job, $message);

        if ($this->debugger) {
            $debug = [
                'app_id'            => Yii::$app->id,
                'time'              => microtime(true),
                'request_id'        => $this->_debug_request_id,
                'request_action'    => $this->_debug_request_action,
                'job'               => is_object($job) ? get_class($job) : null,
                'jobName'           => ($job instanceof RequestNamedJob) ? $job::jobName() : null,
                'rpc_request'       => ($job instanceof RpcRequestJob),
                'rpc_response'      => ($job instanceof RpcResponseJob),
                'message_id'        => $message->getMessageId(),
                'parent_message_id' => $this->_debug_parent_message_id,
                'attempt'           => $message->getProperty(self::PROPERTY_ATTEMPT),
                'persistent'        => $message->getDeliveryMode() == AmqpMessage::DELIVERY_MODE_PERSISTENT,
                'priority'          => $message->getPriority(),
                'ttl'               => $message->getExpiration(),
                'delay'             => $producer->getDeliveryDelay(),
            ];

            if ($target instanceof AmqpTopic) {
                $debug['target_type'] = 'topic';
                $debug['target']      = $target->getTopicName();
            }
            elseif ($target instanceof AmqpQueue) {
                $debug['target_type'] = 'queue';
                $debug['target']      = $target->getQueueName();
            }

            $this->debug('send_start', $debug);
        }
    }

    /**
     * @param BaseJob|null          $job
     * @param AmqpMessage           $message
     * @param AmqpConsumer          $consumer
     * @param \Exception|\Throwable $error
     *
     * @return bool
     * @throws \Exception
     */
    protected function redelivery($job, AmqpMessage $message, AmqpConsumer $consumer, $error)
    {
        $attempt = $message->getProperty(self::PROPERTY_ATTEMPT, 1);

        if ($job && ($job instanceof RetryableJob)) {
            if (!$job->canRetry($attempt, $error)) {
                return false;
            }
        }
        else if ($attempt >= $this->maxAttempts) {
            return false;
        }

        $newMessage = $this->_context->createMessage($message->getBody(), $message->getProperties(), $message->getHeaders());
        $newMessage->setDeliveryMode($message->getDeliveryMode());

        $newMessage->setProperty(self::PROPERTY_ATTEMPT, ++$attempt);

        $this->sendMessage($consumer->getQueue(), $job, $newMessage);

        return true;
    }

    /**
     * @param AmqpDestination $target
     * @param BaseJob|null    $job
     * @param AmqpMessage     $message
     */
    public function beforeSend(AmqpDestination $target, $job, AmqpMessage $message)
    {
        $event = new SendEvent([
            'target'     => $target,
            'requestJob' => $job,
            'message'    => $message,
        ]);

        $this->trigger(static::EVENT_BEFORE_SEND, $event);

        if ($job instanceof Component) {
            $event = new JobEvent([
                'job' => $job,
            ]);

            /* @var Component $job */
            $job->trigger(static::EVENT_BEFORE_SEND, $event);
        }
    }

    /**
     * @param AmqpDestination $target
     * @param BaseJob|null    $job
     * @param AmqpMessage     $message
     */
    public function afterSend(AmqpDestination $target, $job, AmqpMessage $message)
    {
        $event = new SendEvent([
            'target'     => $target,
            'requestJob' => $job,
            'message'    => $message,
        ]);

        $this->trigger(static::EVENT_AFTER_SEND, $event);

        if ($job instanceof Component) {
            $event = new JobEvent([
                'job' => $job,
            ]);

            /* @var Component $job */
            $job->trigger(static::EVENT_AFTER_SEND, $event);
        }
    }

    /**
     * @param ExecuteJob          $requestJob
     * @param RpcResponseJob|null $responseJob
     * @param AmqpMessage         $message
     * @param AmqpConsumer        $consumer
     */
    public function beforeExecute(ExecuteJob $requestJob, $responseJob, AmqpMessage $message, AmqpConsumer $consumer)
    {
        $event = new ExecuteEvent([
            'requestJob'  => $requestJob,
            'responseJob' => $responseJob,
            'message'     => $message,
            'consumer'    => $consumer,
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
     * @param RpcResponseJob|null $responseJob
     * @param AmqpMessage         $message
     * @param AmqpConsumer        $consumer
     */
    public function afterExecute(ExecuteJob $requestJob, $responseJob, AmqpMessage $message, AmqpConsumer $consumer)
    {
        $event = new ExecuteEvent([
            'requestJob'  => $requestJob,
            'responseJob' => $responseJob,
            'message'     => $message,
            'consumer'    => $consumer,
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
     * @param string $type
     * @param mixed  $content
     */
    protected function debug($type, $content)
    {
        if (!$this->debugger) {
            return;
        }

        $this->debugger->log($type, $content);
    }

    protected function debugFlush()
    {
        if (!$this->debugger) {
            return;
        }

        $this->debugger->flush();
    }
}
