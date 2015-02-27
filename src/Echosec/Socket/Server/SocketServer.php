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
		$wampHandler = new WampHandler();

		// Connect to the AMQP broker.
		$amqpConnection = new \AMQPConnection(
			\Config::get('socket::amqp.host'),
			\Config::get('socket::amqp.post'),
			\Config::get('socket::amqp.username'),
			\Config::get('socket::amqp.password'),
			\Config::get('socket::amqp.vhost')
		);

		// Create an AMQP channel.
		$amqpChannel = new AMQPChannel($amqpConnection);

		// Declare an AMQP queue.
		$amqpQueue = new AMQPQueue($amqpChannel);
		$amqpQueue->setName('echosec.ws.queue');
		$amqpQueue->declare();

		// Set up the React AMQP consumer.
		$consumer = new AMQPConsumer($queue, $reactLoop, 0.1, 100); // Poll every 0.1 seconds, retrieve up to 100 queue entries.
		$consumer->on('consume', [$wampHandler, 'onReceiveAmqp']);

		// Sets up a web socket listener on the specified port.
		$webSocket = new \React\Socket\Server($reactLoop);
		$webSocket->listen($port, \Config::get('larapush::socketConnect'));

		// Construct the web server.
		$webServer = new IoServer(
			new HttpServer(
				new WsServer(
					new WampServer(
						$wampHandler
					)
				)
			), $webSocket
		);

		// Run the React event loop... forever.
		$reactLoop->run();
	}
}
