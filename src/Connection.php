<?php

namespace matrozov\yii2amqp;

use Enqueue\AmqpBunny\AmqpConnectionFactory as AmqpBunnyConnectionFactory;
use Enqueue\AmqpExt\AmqpConnectionFactory as AmqpExtConnectionFactory;
use Enqueue\AmqpLib\AmqpConnectionFactory as AmqpLibConnectionFactory;
use Enqueue\AmqpTools\DelayStrategyAware;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Exception;
use Interop\Amqp\AmqpConnectionFactory;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\PsrDestination;
use matrozov\yii2amqp\debugger\Debugger;
use matrozov\yii2amqp\events\ExecuteEvent;
use matrozov\yii2amqp\events\JobEvent;
use matrozov\yii2amqp\events\SendEvent;
use matrozov\yii2amqp\exceptions\NeedRedeliveryException;
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
use matrozov\yii2amqp\jobs\SilentJobException;
use matrozov\yii2amqp\jobs\simple\ExecuteJob;
use matrozov\yii2amqp\jobs\simple\RequestJob;
use matrozov\yii2amqp\serializers\JsonSerializer;
use matrozov\yii2amqp\serializers\Serializer;
use Yii;
use yii\base\Application as BaseApp;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\console\Application as ConsoleApp;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\helpers\Json;
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
 * @property bool           $debugRequestTrace
 * @property bool           $debugRequestTime
 * @property int|float      $debugRequestTimeMin
 */
class Connection extends Component implements BootstrapInterface
{
    const PROPERTY_ATTEMPT     = 'amqp-attempt';
    const PROPERTY_JOB_NAME    = 'amqp-job-name';
    const PROPERTY_TRACE       = 'amqp-trace';
    const PROPERTY_TRACE_CHILD = 'amqp-request-trace';

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
     * @var bool
     */
    public $debugRequestTrace = false;

    /**
     * @var bool
     */
    public $debugRequestTime  = false;

    /**
     * @var int|float|null
     */
    public $debugRequestTimeMin = null;

    /**
     * @var array $_trace
     * @var array $_traceItem
     * @var float $_traceStart
     */
    protected $_trace      = [];
    protected $_traceItem  = [];
    protected $_traceStart = 0;

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
        }

        Event::on(BaseApp::class, BaseApp::EVENT_AFTER_REQUEST, function () {
            $this->close();

            $this->debugFlush();
        });

        Event::on(static::class, static::EVENT_AFTER_EXECUTE, function () {
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
            return;
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
            $this->_context->setDelayStrategy(new RabbitMqDlxDelayStrategy());
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
     * @param PsrDestination $target
     * @param RpcRequestJob  $job
     *
     * @return RpcResponseJob|bool|null
     * @throws
     */
    protected function sendRpcMessage(PsrDestination $target, RpcRequestJob $job)
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

        $queue = $this->_context->createQueue(uniqid($exchangeName . '_rpc_callback_', true));
        $queue->addFlag(AmqpQueue::FLAG_IFUNUSED);
        $queue->addFlag(AmqpQueue::FLAG_AUTODELETE);
        $queue->addFlag(AmqpQueue::FLAG_EXCLUSIVE);
        $queue->setArgument('x-expires', (int)$this->rpcTimeout * 1000 * 2);
        $this->_context->declareQueue($queue);

        $message->setReplyTo($queue->getQueueName());
        $message->setCorrelationId(uniqid('', true));

        $this->sendMessage($target, $job, $message);

        $consumer = $this->_context->createConsumer($queue);

        $timeout = $this->rpcTimeout;

        while (true) {
            $start = microtime(true);

            $responseMessage = $consumer->receive((int)$timeout * 1000);

            if (!$responseMessage) {
                throw new RpcTimeoutException('Queue timeout!');
            }

            if (!$message->getCorrelationId() != $responseMessage->getCorrelationId()) {
                if ($timeout !== null) {
                    $timeout -= (microtime(true) - $start);

                    if ($timeout < 0) {
                        throw new RpcTimeoutException('Queue timeout!');
                    }
                }

                $consumer->reject($responseMessage, false);

                continue;
            }

            $consumer->acknowledge($responseMessage);

            $this->_context->unsubscribe($consumer);

            // Catch rpc sub-trace
            if ($this->debugRequestTrace && !empty($childTrace = $responseMessage->getProperty(self::PROPERTY_TRACE))) {
                $this->_traceItem['child'] = Json::decode($childTrace);
            }

            $responseJob = $this->serializer->deserialize($responseMessage->getBody());

            if (!($responseJob instanceof RpcResponseJob)) {
                throw new ErrorException('Root object must be RpcResponseJob!');
            }

            if ($responseJob instanceof RpcFalseResponseJob) {
                return false;
            }

            if ($responseJob instanceof RpcExceptionResponseJob) {
                throw $responseJob->exception();
            }

            return $responseJob;
        }

        $this->_context->unsubscribe($consumer);

        return null;
    }

    /**
     * @param PsrDestination $target
     * @param BaseJob        $job
     *
     * @return bool
     * @throws
     */
    protected function sendSimpleMessage(PsrDestination $target, BaseJob $job)
    {
        $message = $this->createMessage($job);

        $this->sendMessage($target, $job, $message);

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

        if ($this->debugRequestTrace) {
            // Trace SEND start

            $this->_traceItem = [
                'app'      => Yii::$app->id,
                'exchange' => $exchangeName,
                'job'      => get_class($job),
            ];

            $this->_traceStart = microtime(true);

            if ($job instanceof RequestNamedJob) {
                $this->_traceItem['jobName'] = $job::jobName();
            }

            if ($job instanceof RpcRequestJob) {
                $this->_traceItem['rpc'] = true;
            }
        }

        if ($job instanceof RpcRequestJob) {
            $result = $this->sendRpcMessage($exchange, $job);
        }
        else {
            $result = $this->sendSimpleMessage($exchange, $job);
        }

        if ($this->debugRequestTrace) {
            // Trace SEND stop

            $this->_traceItem['time'] = microtime(true) - $this->_traceStart;
            $this->_traceItem['res']  = $result !== false;

            if ($this->debugRequestTime && (($this->debugRequestTimeMin === null) || ($this->_traceItem['time'] > $this->debugRequestTimeMin))) {
                $item = $this->_traceItem;

                if (isset($item['child'])) {
                    $item['child'] = Json::encode($item['child']);
                }

                $this->debug('request-time', $item);
            }

            $this->_trace[] = $this->_traceItem;
        }

        return $result;
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

        return $this->sendSimpleMessage($queue, $responseJob);
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
        $oldDebugRequestTrace = $this->debugRequestTrace;

        $this->debugRequestTrace = $this->debugRequestTrace || $message->getProperty(self::PROPERTY_TRACE_CHILD, false);

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
                $exceptionInt = $this->handleRpcMessageException($exception, $job, $message, $consumer);
            }

            $this->afterExecute($job, $responseJob, $message, $consumer);
        }
        catch (\Exception $exception) {
            if (!$exceptionInt) {
                $exceptionExt = $this->handleRpcMessageException($exception, $job, $message, $consumer);
            }
        }

        if ($exceptionInt || $exceptionExt) {
            throw $exceptionInt ? $exceptionInt : $exceptionExt;
        }

        $this->debugRequestTrace = $oldDebugRequestTrace;

        $consumer->acknowledge($message);
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
        if ((!($exception instanceof HttpException)) && (!($exception instanceof RpcTransferableException))) {
            if ($this->redelivery($job, $message, $consumer, $exception)) {
                $consumer->acknowledge($message);
            }
            else {
                $responseJob = new RpcFalseResponseJob();

                $this->replyRpcMessage($message, $responseJob);

                $consumer->reject($message, false);
            }

            return $exception;
        }

        $responseJob = new RpcExceptionResponseJob($exception);

        $this->replyRpcMessage($message, $responseJob);

        Yii::$app->getErrorHandler()->logException($exception);

        return null;
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
            $this->beforeExecute($job, null, $message, $consumer);

            $job->execute($this, $message);

            $this->afterExecute($job, null, $message, $consumer);
        }
        catch (\Exception $e) {
            if ($this->redelivery($job, $message, $consumer, $e)) {
                $consumer->acknowledge($message);
            }
            else {
                $consumer->reject($message, false);
            }

            if ($e instanceof SilentJobException) {
                Yii::$app->getErrorHandler()->logException($e);

                return;
            }
            else {
                throw $e;
            }
        }

        $consumer->acknowledge($message);
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

        $job = $this->serializer->deserialize($message->getBody(), $jobClassName);

        /* @var ExecuteJob $job */
        if (!($job instanceof ExecuteJob)) {
            $consumer->reject($message, false);

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

        foreach ((array)$queueNames as $queueName) {
            if (!isset($this->_queues[$queueName])) {
                throw new ErrorException('Queue config `' . $queueName . '` not found!');
            }

            $consumer = $this->_context->createConsumer($this->_queues[$queueName]);

            $this->_context->subscribe($consumer, function(AmqpMessage $message, AmqpConsumer $consumer) {
                $this->handleMessage($message, $consumer);

                return true;
            });
        }

        while (true) {
            $start = microtime(true);

            $loopTimeout = max(5, (int)$timeout);

            $this->_context->consume($loopTimeout * 1000);

            if ($timeout !== null) {
                $timeout -= microtime(true) - $start;
            }

            if ((($timeout !== null) && ($timeout < 0)) || ExitSignal::isExit()) {
                break;
            }
        }
    }

    /**
     * @param PsrDestination $target
     * @param BaseJob        $job
     * @param AmqpMessage    $message
     *
     * @throws
     */
    protected function sendMessage(PsrDestination $target, BaseJob $job, AmqpMessage $message)
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
                $message->setExpiration($ttl);
            }
            elseif ($this->ttl !== null) {
                $message->setExpiration($this->ttl);
            }
        }

        if (($job instanceof DelayedJob) && (($delay = $job->getDelay()) !== null)) {
            $producer->setDeliveryDelay($delay * 1000);
        }

        if ($this->debugRequestTrace) {
            if ($message->getDeliveryMode() == AmqpMessage::DELIVERY_MODE_PERSISTENT) {
                $this->_traceItem['persistent'] = true;
            }

            if (($priority = $message->getPriority()) !== 0) {
                $this->_traceItem['priority'] = $priority;
            }

            if (($ttl = $message->getExpiration()) !== 0) {
                $this->_traceItem['ttl'] = $ttl;
            }

            if (($delay = $producer->getDeliveryDelay()) !== null) {
                $this->_traceItem['delay'] = $delay;
            }

            if (($attempt = $message->getProperty(self::PROPERTY_ATTEMPT)) !== null) {
                $this->_traceItem['attempt'] = $attempt;
            }

            if ($job instanceof RpcRequestJob) {
                $message->setProperty(self::PROPERTY_TRACE_CHILD, true);
            }

            if (($job instanceof RpcResponseJob) && !empty($this->_trace)) {
                $message->setProperty(self::PROPERTY_TRACE, Json::encode($this->_trace));
            }
        }

        $this->beforeSend($target, $job, $message);

        $producer->send($target, $message);

        $this->afterSend($target, $job, $message);
    }

    /**
     * @param BaseJob               $job
     * @param AmqpMessage           $message
     * @param AmqpConsumer          $consumer
     * @param \Exception|\Throwable $error
     *
     * @return bool
     */
    protected function redelivery(BaseJob $job, AmqpMessage $message, AmqpConsumer $consumer, $error)
    {
        $attempt = $message->getProperty(self::PROPERTY_ATTEMPT, 1);

        if (!($error instanceof NeedRedeliveryException)) {
            if ($job instanceof RetryableJob) {
                if (!$job->canRetry($attempt, $error)) {
                    return false;
                }
            }
            else if ($attempt >= $this->maxAttempts) {
                return false;
            }
        }

        $newMessage = $this->_context->createMessage($message->getBody(), $message->getProperties(), $message->getHeaders());
        $newMessage->setDeliveryMode($message->getDeliveryMode());

        $newMessage->setProperty(self::PROPERTY_ATTEMPT, ++$attempt);

        $this->sendMessage($consumer->getQueue(), $job, $newMessage);

        return true;
    }

    /**
     * @param PsrDestination $target
     * @param BaseJob        $job
     * @param AmqpMessage    $message
     */
    public function beforeSend(PsrDestination $target, BaseJob $job, AmqpMessage $message)
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
     * @param PsrDestination $target
     * @param BaseJob        $job
     * @param AmqpMessage    $message
     */
    public function afterSend(PsrDestination $target, BaseJob $job, AmqpMessage $message)
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

    protected function debugTrace()
    {
        $this->debug('request-trace', $this->_trace);

        $this->_trace = [];
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

        if ($this->debugRequestTrace && !empty($this->_trace)) {
            $this->debugTrace();
        }

        $this->debugger->flush();
    }
}