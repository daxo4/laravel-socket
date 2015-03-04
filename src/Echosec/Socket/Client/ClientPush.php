<?php namespace Echosec\Socket\Client;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
Allows a Laravel RESTful web server to push messages to the web socket server.
*/
class ClientPush {
	/**
	Storage for AMQP components.
	*/
	protected $amqpConnection;
	protected $amqpChannel;
	protected $amqpQueue;
	protected $syncQueue;

	public function __construct()
	{
		$this->amqpConnection = new AMQPConnection(
                        \Config::get('socket::amqp.host'),
                        \Config::get('socket::amqp.port'),
                        \Config::get('socket::amqp.username'),
                        \Config::get('socket::amqp.password'),
                        \Config::get('socket::amqp.vhost')
                );
                $this->amqpChannel = $this->amqpConnection->channel();

                $this->amqpQueue = 'echosec.ws.queue'; // TODO Move to config.
                $this->amqpChannel->queue_declare($this->amqpQueue, false, true, false, true);

                $this->syncQueue = 'echosec.ws.sync'; // TODO Move to config.
                $this->amqpChannel->queue_declare($this->amqpQueue, false, true, false, true);
	}

	/**
	Pushes a message via web socket.

	@param string $data The data packet to send.
	@param string $topic The topic to which the message will be sent.
	@param array $users An array of users to send the message to. If empty, will send to all users.
	*/
	public function send($data, $topic, array $users = array())
	{
		$payload = json_encode(array(
			'data'=>$data,
			'topic'=>$topic
		));
		$message = new AMQPMessage($payload, array('delivery_mode' => 2));
		$this->amqpChannel->basic_publish($message, '', $this->amqpQueue);
	}

	/**
	Signal the web socket server that a user has logged on.

	@param string $wsSession The web socket session ID to sync.
	@param string $userId The user ID to sync.
	*/
	public function login($wsSession, $userId)
	{
		$this->sync('add', $wsSession, $userId);
	}	

	/**
	Signal the web socket server that a user is logging out.
	*/
	public function logout()
	{
		$this->sync('remove');
	}

	/**
	Pushes a request to synchronize a user's session and user IDs on the server.
	If the user authenticates via a RESTful endpoint on the LAMP server, we use this command to pass that authenticated session to the web socket server.

	@param string $type The type of sync action, one of 'add' or 'remove'.
	@param string $wsSession The web socket session ID to sync with the currently authenticated user.
	*/
	private function sync($type, $wsSession, $userId)
	{
		$payload = json_encode(array(
			'sync'=>true,
			'type'=>$type,
			'sessionId'=>$wsSession,
			'userId'=>$userId
		));
		$message = new AMQPMessage($payload, array('delivery_mode' => 2));
		$this->amqpChannel->basic_publish($message, '', $this->syncQueue);
	}
}
