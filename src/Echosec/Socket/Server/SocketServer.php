<?php namespace Echosec\Socket\Server;

use JCook\ReactAMQP\Consumer as AMQPConsumer;

use React\EventLoop\Factory;
use React\Socket\Server;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class SocketServer {

	/**
	Runs a React web server with appropriate alert hooks.
	*/
	public function run($port)
	{
		$reactLoop = \React\EventLoop\Factory::create();

		// Connect to the AMQP broker.
		$amqpConnection = new AMQPConnection();
		$amqpConnection->setHost(\Config::get('larapush::amqpHost'));
		$amqpConnection->setPort(\Config::get('larapush::amqpPort'));
		$amqpConnection->setVhost(\Config::get('larapush::amqpVhost'));
		$amqpConnection->setLogin(\Config::get('larapush::amqpUser'));
		$amqpConnection->setPassword(\Config::get('larapush::amqpPassword'));
		$amqpConnection->connect();

		// Create an AMQP channel.
		$amqpChannel = new AMQPChannel($amqpConnection);

		// Declare an AMQP queue.
		$amqpQueue = new AMQPQueue($amqpChannel);
		$amqpQueue->setName('echosec.ws.queue');
		$amqpQueue->declare();

		// Set up the React AMQP consumer.
		$consumer = new AMQPConsumer($queue, $reactLoop, 0.1, 100); // Poll every 0.1 seconds, retrieve up to 100 queue entries.
		$consumer->on('consume', [new SocketServerSender(), 'serverReceiveAmqp']);

		// Sets up a web socket listener on the specified port.
		$webSocket = new \React\Socket\Server($reactLoop);
		$webSocket->listen($port, \Config::get('larapush::socketConnect'));

		// Construct the web server.
		$webServer = new IoServer(
			new HttpServer(
				new WsServer(
					// TODO
				)
			), $webSocket
		);

		// Run the React event loop... forever.
		$reactLoop->run();
	}
}
