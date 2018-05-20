<?php
namespace matrozov\yii2amqp;

use Yii;
use yii\base\Event;
use yii\base\BaseObject;
use yii\base\Application as BaseApp;
use yii\console\Application as ConsoleApp;
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
use Enqueue\AmqpLib\AmqpConnectionFactory;
use Interop\Queue\PsrDestination;

/**
 * Class Connection
 * @package matrozov\yii2amqp
 *
 * @property string  $dsn        AMQP Server dsn
 * @property string  $host       AMQP Server host
 * @property int     $port       AMQP Server port (default = 5672)
 * @property string  $user       AMQP Server username (default = guest)
 * @property string  $password   AMQP Server port (default = guest)
 * @property string  $vhost      RabbitMQ vhost
 *
 * @property []array $exchanges  Exchange config list
 * @property []array $queues     Queue config list
 * @property []array $bindings   Binding config list
 */
class Connection extends BaseObject implements BootstrapInterface
{
    /* @var string $dsn AMQP DSN */
    public $dsn;

    /* @var string $host AMQP Server host */
    public $host;

    /* @var int $port AMQP Server port (default = 5672) */
    public $port = 5672;

    /* @var string $user AMQP Server username (default = guest) */
    public $user = 'guest';

    /* @var string $password AMQP Server port (default = guest) */
    public $password = 'guest';

    /* @var string $vhost RabbitMQ vhost */
    public $vhost = '/';


    /* @var []array $exchanges Exchange config list */
    public $exchanges = [];

    /* @var []array $queues Queue config list */
    public $queues = [];

    /* @var []array $bindings Binding config list */
    public $bindings = [];


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
            'class'      => Command::class,
            'connection' => $this,
        ];
    }

    /**
     * Open amqp connection
     *
     * @throws
     */
    public function open() {
        if ($this->_context) {
            $this->close();
        }

        $config = [
            'dsn'   => $this->dsn,
            'host'  => $this->host,
            'port'  => $this->port,
            'user'  => $this->user,
            'pass'  => $this->password,
            'vhost' => $this->vhost,
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
    public function close() {
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
    protected function setup() {
        foreach ($this->queues as $queueConfig) {
            $queue = $this->_context->createQueue($queueConfig['name']);
            $queue->addFlag(AmqpQueue::FLAG_DURABLE);
            $this->_context->declareQueue($queue);

            $this->_queues[$queueConfig['name']] = $queue;
        }

        foreach ($this->exchanges as $exchangeConfig) {
            $exchange = $this->_context->createTopic($exchangeConfig['name']);
            $exchange->setType(AmqpTopic::TYPE_DIRECT);
            $exchange->addFlag(AmqpTopic::FLAG_DURABLE);
            $this->_context->declareTopic($exchange);

            $this->_exchanges[$exchangeConfig['name']] = $exchange;
        }

        foreach ($this->bindings as $bind) {
            if (!isset($this->_queues[$bind['queue']])) {
                throw new ErrorException('Can\'t bind unknown Queue!');
            }

            if (!isset($this->_exchanges[$bind['exchange']])) {
                throw new ErrorException('Can\'t bind unknown Exchange!');
            }

            $this->_context->bind(new AmqpBind($this->_queues[$bind['queue']], $this->_exchanges[$bind['exchange']]));
        }
    }

    /**
     * @param BaseJob $job
     *
     * @return AmqpMessage
     * @throws
     */
    protected function createMessage(BaseJob $job) {
        $message = $this->_context->createMessage();
        $message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);
        $message->setMessageId(uniqid('', true));
        $message->setTimestamp(time());
        $message->setBody($job->encode());
        $message->setContentType('application/json');

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
    protected function internalRpcSend(PsrDestination $target, RpcRequestJob $job, $timeout = 0) {
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

            $responseJob = RpcResponseJob::decode($responseMessage->getBody());

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
    protected function internalSend(PsrDestination $target, BaseJob $job) {
        $message = $this->createMessage($job);

        $producer = $this->_context->createProducer();
        $producer->send($target, $message);

        return true;
    }

    /**
     * @param string      $targetName
     * @param ExecutedJob $job
     * @param int         $timeout
     *
     * @return RpcResponseJob|bool|null
     * @throws
     */
    public function send($exchangeName, ExecutedJob $job, $timeout = 0) {
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
     */
    protected function handleMessage(AmqpMessage $message) {
        $job = BaseJob::decode($message->getBody());

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
    public function listen($queueNames = null, $timeout = 0) {
        $this->open();

        if (empty($queueNames)) {
            $queueNames = array_keys($this->_queues);
        }

        foreach ($queueNames as $queueName) {
            if (!isset($this->_queues[$queueName])) {
                throw new ErrorException('Queue config `' + $queueName + '` not found!');
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