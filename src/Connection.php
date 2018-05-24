<?php
namespace matrozov\yii2amqp;

use matrozov\yii2amqp\jobs\BaseJob;
use matrozov\yii2amqp\jobs\ExecutedJob;
use matrozov\yii2amqp\jobs\RpcRequestJob;
use matrozov\yii2amqp\jobs\RpcResponseJob;
use matrozov\yii2amqp\serializers\JsonSerializer;
use matrozov\yii2amqp\serializers\Serializer;
use Yii;
use yii\base\Event;
use yii\base\BaseObject;
use yii\base\Application as BaseApp;
use yii\base\InvalidConfigException;
use yii\console\Application as ConsoleApp;
use yii\base\BootstrapInterface;
use yii\base\ErrorException;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Enqueue\AmqpLib\AmqpConnectionFactory;
use Interop\Queue\PsrDestination;

/**
 * Class Connection
 * @package matrozov\yii2amqp
 *
 * @property string|null    $dsn                AMQP Server dsn
 * @property string|null    $host               AMQP Server host
 * @property int|null       $port               AMQP Server port (default = 5672)
 * @property string|null    $user               AMQP Server username (default = guest)
 * @property string|null    $password           AMQP Server port (default = guest)
 * @property string|null    $vhost              RabbitMQ vhost
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
 * @property []array        $exchanges          Exchange config list
 * @property []array        $queues             Queue config list
 * @property []array        $bindings           Binding config list
 *
 * @property []array        $defaultQueue       Default Exchange config
 * @property []array        $defaultExchange    Default Exchange config
 * @property []array        $defaultBind        Default Bind config
 *
 * @property int            $rpcTimeout         Default wait rpc response timeout
 *
 * @property Serializer     $serializer         Serializer
 */
class Connection extends BaseObject implements BootstrapInterface
{
    /* @var string|null $dsn AMQP DSN */
    public $dsn;

    /* @var string|null $host AMQP Server host */
    public $host;

    /* @var int|null $port AMQP Server port (default = 5672) */
    public $port = 5672;

    /* @var string|null $user AMQP Server username (default = guest) */
    public $user = 'guest';

    /* @var string|null $password AMQP Server port (default = guest) */
    public $password = 'guest';

    /* @var string|null $vhost RabbitMQ vhost */
    public $vhost;


    /* @var float|null $readTimeout */
    public $readTimeout;

    /* @var float|null $writeTimeout */
    public $writeTimeout;

    /* @var float|null $connectionTimeout */
    public $connectionTimeout;


    /* @var float|null $heartbeat */
    public $heartbeat;

    /* @var bool|null $persisted */
    public $persisted;

    /* @var bool|null $lazy */
    public $lazy;


    /* @var bool|null $qosGlobal */
    public $qosGlobal;

    /* @var int|null $qosPrefetchSize */
    public $qosPrefetchSize;

    /* @var int|null $qosPrefetchCount */
    public $qosPrefetchCount;


    /* @var bool|null $sslOn */
    public $sslOn;

    /* @var bool|null $sslVerify */
    public $sslVerify;

    /* @var string|null $sslCacert */
    public $sslCacert;

    /* @var string|null $sslCert */
    public $sslCert;

    /* @var string|null $sslKey */
    public $sslKey;


    /* @var []array $queues Queue config list */
    public $queues = [];

    /* @var []array $exchanges Exchange config list */
    public $exchanges = [];

    /* @var []array $bindings Binding config list */
    public $bindings = [];


    /* @var []array $defaultQueue Default Queue config */
    public $defaultQueue = [
        'flags' => AmqpQueue::FLAG_DURABLE,
    ];

    /* @var []array $defaultExchange Default Exchange config */
    public $defaultExchange = [
        'type'  => AmqpTopic::TYPE_DIRECT,
        'flags' => AmqpTopic::FLAG_DURABLE,
    ];

    /* @var []array $defaultBind Default Bind config */
    public $defaultBind = [
        'routingKey' => null,
        'flags'      => AmqpBind::FLAG_NOPARAM,
        'arguments'  => [],
    ];


    /* @var int $rpcTimeout Default wait rpc response timeout */
    public $rpcTimeout = 5000;


    /* @var Serializer $serializer */
    public $serializer = JsonSerializer::class;


    /* @var AmqpContext $_context */
    protected $_context;

    /* @var []AmqpQueue $_queues Queue list */
    protected $_queues = [];

    /* @var []AmqpTopic $_exchanges Exchange list */
    protected $_exchanges = [];


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->serializer = Instance::ensure($this->serializer, Serializer::class);

        Event::on(BaseApp::class, BaseApp::EVENT_AFTER_REQUEST, function () {
            $this->close();
        });
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
                if (!isset($queueConfig[$field])) {
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
                if (!isset($exchangeConfig[$field])) {
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
            $bindConfig = ArrayHelper::merge($this->defaultQueue, $bindConfig);

            foreach (['queue', 'exchange', 'routingKey', 'flags', 'arguments'] as $field) {
                if (!isset($bindConfig[$field])) {
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

        $message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);
        $message->setMessageId(uniqid('', true));
        $message->setTimestamp(time());

        $message->setBody($this->serializer->serialize($job));

        if (!empty($this->serializer->contentType())) {
            $message->setContentType($this->serializer->contentType());
        }

        return $message;
    }

    /**
     * @param PsrDestination $target
     * @param RpcRequestJob  $job
     * @param int            $timeout
     *
     * @return RpcResponseJob|bool|null
     * @throws
     */
    protected function internalRpcSend(PsrDestination $target, RpcRequestJob $job, $timeout = null)
    {
        if ($timeout === null) {
            $timeout = $this->rpcTimeout;
        }

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
            $responseMessage = $consumer->receive($timeout);

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
    protected function internalSend(PsrDestination $target, BaseJob $job)
    {
        $message = $this->createMessage($job);

        $producer = $this->_context->createProducer();
        $producer->send($target, $message);

        return true;
    }

    /**
     * @param string      $exchangeName
     * @param ExecutedJob $job
     * @param int         $timeout
     *
     * @return RpcResponseJob|bool|null
     * @throws
     */
    public function send($exchangeName, ExecutedJob $job, $timeout = null)
    {
        $this->open();

        if (!isset($this->_exchanges[$exchangeName])) {
            throw new ErrorException('Exchange with name `' . $exchangeName . '` not found!');
        }

        $exchange = $this->_exchanges[$exchangeName];

        if ($job instanceof RpcRequestJob) {
            return $this->internalRpcSend($exchange, $job, $timeout);
        }
        else {
            return $this->internalSend($exchange, $job);
        }
    }

    /**
     * @param AmqpMessage $message
     *
     * @return bool
     * @throws
     */
    protected function handleMessage(AmqpMessage $message)
    {
        $job = $this->serializer->deserialize($message->getBody());

        if ($job instanceof RpcRequestJob) {
            $responseJob = $job->execute();

            if (!$responseJob || !($responseJob instanceof RpcResponseJob)) {
                throw new ErrorException('You must return response RpcResponseJob for RpcRequestJob!');
            }

            $queueName = $message->getReplyTo();

            $queue = $this->_context->createQueue($queueName);

            return $this->internalSend($queue, $responseJob);
        }
        elseif ($job instanceof ExecutedJob) {
            $job->execute();

            return true;
        }

        return false;
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
                if ($this->handleMessage($message)) {
                    $consumer->acknowledge($message);
                }
                else {
                    $consumer->reject($message, false);
                }

                return true;
            });
        }

        $this->_context->consume($timeout);
    }
}