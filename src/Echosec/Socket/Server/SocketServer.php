<?php namespace Echosec\Socket\Server;

use JCook\ReactAMQP\Consumer as AMQPConsumer;

use React\EventLoop\Factory;
use React\Socket\Server;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Wamp\WampServer;

class SocketServer {

	/**
	Runs a React web server with appropriate alert hooks.
	*/
	public function run($port)
	{
		$reactLoop = \React\EventLoop\Factory::create();

		// Connect to the AMQP broker.
		$amqpConnection = new AMQPConnection();
		$amqpConnection->setHost(\Config::get('socket-laravel::amqp.host'));
		$amqpConnection->setPort(\Config::get('socket-laravel::amqp.post'));
		$amqpConnection->setVhost(\Config::get('socket-laravel::amqp.vhost'));
		$amqpConnection->setLogin(\Config::get('socket-laravel::amqp.username'));
		$amqpConnection->setPassword(\Config::get('socket-laravel::amqp.password'));
		$amqpConnection->connect();

		// Create an AMQP channel.
		$amqpChannel = new AMQPChannel($amqpConnection);

		// Declare an AMQP queue.
		$amqpQueue = new AMQPQueue($amqpChannel);
		$amqpQueue->setName('echosec.ws.queue');
		$amqpQueue->declare();

		// Set up the React AMQP consumer.
		$consumer = new AMQPConsumer($queue, $reactLoop, 0.1, 100); // Poll every 0.1 seconds, retrieve up to 100 queue entries.
		$consumer->on('consume', [new AmqpHandler(), 'receiveAmqp']);

		// Sets up a web socket listener on the specified port.
		$webSocket = new \React\Socket\Server($reactLoop);
		$webSocket->listen($port, \Config::get('larapush::socketConnect'));

		// Construct the web server.
		$webServer = new IoServer(
			new HttpServer(
				new WsServer(
					new WampServer(
						new WampHandler()
					)
				)
			), $webSocket
		);

		// Run the React event loop... forever.
		$reactLoop->run();
	}
}
