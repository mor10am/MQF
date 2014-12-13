<?php

require_once 'Zend/Validate/Hostname.php';
require_once 'MQF/ESB/Connector/Stomp.php';
require_once 'MQF/ESB/Message.php';

/**
*
*
*/
final class MQF_ESB
{
    private $stomp         = null;
    private $options       = array();
    private $current_queue = '';
    private $handler       = null;

    private $debug = false;

    private $connected = false;

    private $prefetchlimit = 1;
    private $persistent    = false;

    private $_messages_handled = 0;

    /**
    *
    */
    public function __construct($esbhandler = null, $options = array())
    {
        if (!is_array($options)) {
            throw new Exception("Options were not given as array");
        }

        if (!isset($options['brokeruri'])) {
            throw new Exception("URI for ActiveMQ server not specified!");
        }

        if (!isset($options['username']) or !isset($options['password'])) {
            throw new Exception("Username or password was not given for ESB");
        }

        if (isset($options['prefetch-limit'])) {
            $limit = $options['prefetch-limit'];
            if ($limit < 1 or $limit > 100) {
                throw new Exception("Prefetch limit must be between 1 and 100 messages");
            }
            $this->prefetchlimit = $limit;
            MQF_Log::log("Setting prefetch limit to $limit");
        }

        if (isset($options['debug'])) {
            $this->debug = $options['debug'];
        }

        if (isset($options['persistent'])) {
            $this->persistent = $options['persistent'];
        }

        $this->options = $options;

        if ($esbhandler == null) {
            $this->handler = $this;
            MQF_Log::log("Using self as ESB handler. No processing will be done for messages");
        } elseif ($esbhandler instanceof MQF_ESB_Handler) {
            $this->handler = $esbhandler;
        } else {
            throw new Exception("ESB handler object not defined!");
        }

        $this->stomp = new MQF_ESB_Connector_Stomp($options['brokeruri'], $options);
    }

    /**
    *
    */
    public function __destruct()
    {
        try {
            $this->stop();
        } catch (Exception $e) {
        }
    }

    /**
    *
    */
    public function stop()
    {
        if ($this->connected) {
            if ($this->stomp instanceof MQF_ESB_Connector_Stomp) {
                $this->stomp->unsubscribe($this->current_queue);
                $this->stomp->disconnect();
            }

            $this->connected = false;
        }
    }

    /**
    *
    */
    public function handle(MQF_ESB_Connector_Stomp_Frame $frame)
    {
        MQF_Log::log('Not doing anything to message '.$frame->getMessageId());

        return new MQF_ESB_Message('NOOP');
    }

    /**
    *
    */
    private function _connect()
    {
        if ($this->connected) {
            return true;
        }

        if ($this->stomp->connect($this->options['username'], $this->options['password'])) {
            if ($this->current_queue != '') {
                $this->useQueue($this->current_queue);
            }

            $this->connected = true;
        }
    }

    /**
    *
    */
    public function useQueue($queue, $options = array())
    {
        $queue = trim($queue);

        if (substr($queue, 0, 7) != '/queue/') {
            if (!strlen($queue)) {
                throw new Exception("Unable to use queue named '$queue'");
            }
            $queue = '/queue/'.$queue;
        }

        $this->_connect();

        if ($queue != $this->current_queue and $this->current_queue != '') {
            $this->stomp->unsubscribe($this->current_queue);
            $this->current_queue = '';
        }

        $options = array_merge($options, array('activemq.exclusive' => true, 'activemq.prefetchSize' => $this->prefetchlimit));

        $frame = $this->stomp->subscribe($queue, $options);

        $this->current_queue = $queue;

        MQF_Log::log("Subscribed to queue '$queue'");
    }

    /**
    *
    */
    public function handleQueue($max_messages = -1, $queue = false)
    {
        if ($max_messages != -1) {
            if ($max_messages < $this->prefetchlimit) {
                $this->stop();
                throw new Exception("Max messages={$max_messages} to get must be same or higher than prefetch-limit={$this->prefetchlimit}");
            }
        }

        if ($queue !== false) {
            $this->useQueue($queue);
        }

        if ($this->current_queue == '') {
            throw new Exception("ESB is not subscribing to any queue");
        }

        $this->_connect();

        $this->_messages_handled = 0;

        $no_msg_count = 0;

        while ($frame = $this->stomp->readFrame()) {
            if (!$frame instanceof MQF_ESB_Connector_Stomp_Frame) {
                $no_msg_count++;

                if ($no_msg_count >= 5) {
                    MQF_Log::log("Did not get a message ({$no_msg_count}). Will disconnect/connect and try again...", MQF_WARN);

                    $queue_tmp_var = $this->current_queue;
                    $this->stop();

                    sleep(3);

                    $this->useQueue($queue_tmp_var);

                    continue;
                } else {
                    MQF_Log::log("Did not get a message after {$no_msg_count} retries. Will stop...", MQF_WARN);
                    break;
                }
            }

            $no_msg_count = 0;

            if ($max_messages != -1 and ($this->_messages_handled > $max_messages or $this->_messages_handled > 1000)) {
                MQF_Log::log("Reached max messages ($max_messages) to handle in this loop for queue '{$this->current_queue}'");
                break;
            }

            $this->_messages_handled++;

            $id = $frame->getMessageId();

            try {
                $esbmsg = $this->handler->handle($frame);
            } catch (Exception $e) {
                MQF_Log::log($e->getMessage(), MQF_ERROR);

                MQF_Log::log(print_r($frame, true));

                continue;
            }

            if (!$esbmsg instanceof MQF_ESB_Message) {
                throw new Exception("Handler does not return MQF_ESB_Message object!");
            }

            $cmd = $esbmsg->getCommand();

            switch ($cmd) {
            case 'MOVE':
                $new_queue = $esbmsg->getField('QUEUE');

                if ($new_queue != $this->current_queue) {
                    $this->addMessage($frame->getBody(), array('queue' => $new_queue));

                    $ack = true;

                    if ($esbmsg->hasField('ACKORG') and $esbmsg->getField('ACKORG') === false) {
                        $ack = false;
                    }

                    MQF_Log::log("Copied message '$id' from '{$this->current_queue}' to '$new_queue'");

                    if ($ack) {
                        $this->stomp->acknowledge($id);
                        MQF_Log::log("Message '$id' was removed from '{$this->current_queue}'");
                    } else {
                        MQF_Log::log("Message '$id' was not removed from '{$this->current_queue}'");
                    }
                } else {
                    MQF_Log::log("Skipped trying to move message to same queue '{$this->current_queue}'", MQF_WARN);
                }
                break;
            case 'ACK':
                $this->stomp->acknowledge($id);
                break;
            case 'ADD':
                MQF_Log::log("Not implemented", MQF_WARN);
                break;
            case 'NOOP':
                break;
            default:
                throw new Exception("Don't know what to do for command '$cmd'");
            }
        }

        MQF_Log::log("Handled ".($this->_messages_handled - 1)." message(s) in queue '{$this->current_queue}'");

        $this->stop();
    }

    /**
     *
     */
    public function getMessagesHandledCount()
    {
        return $this->_messages_handled;
    }

    /**
    *
    */
    public function addMessage($msg, $options = array())
    {
        $queue = false;

        if (isset($options['queue'])) {
            $queue = $options['queue'];
            unset($options['queue']);
        }

        if (!$queue) {
            if ($this->current_queue == '') {
                throw new Exception("ESB is not subscribing to any queue");
            } else {
                $queue = $this->current_queue;
            }
        } else {
            $queue = trim($queue);

            if (substr($queue, 0, 7) != '/queue/') {
                if (!strlen($queue)) {
                    throw new Exception("Unable to use queue name '$queue'");
                }

                $queue = '/queue/'.$queue;
            }
        }

        if (is_array($msg) or is_object($msg)) {
            $options['amq-msg-type'] = 'MapMessage';
        } else {
            $reader = new XMLReader();

            if (!$reader->XML($msg)) {
                throw new Exception("Data for queue '$queue' was not a valid XML document");
            }
        }

        $this->_connect();

        if ($this->persistent and !isset($options['persistent'])) {
            $options['persistent'] = 'true';
        }

        if ($options['persistent'] === true) {
            $options['persistent'] = 'true';
        }

        $frame = $this->stomp->send($queue, $msg, $options);

        MQF_Log::log("Added message to queue '$queue'");

        return $frame;
    }
}
