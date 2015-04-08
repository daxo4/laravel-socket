<?php namespace Echosec\Socket\Server;

use PhpAmqpLib\Connection\AMQPConnection;
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
		$amqpConnection = new AMQPConnection(
			\Config::get('socket::amqp.host'),
			\Config::get('socket::amqp.port'),
			\Config::get('socket::amqp.username'),
			\Config::get('socket::amqp.password'),
			\Config::get('socket::amqp.vhost')
		);

		// Create an AMQP channel.
		$amqpChannel = $amqpConnection->channel();

		// Set up the core React AMQP consumer, with a dedicated queue.
		$amqpQueue = \Config::get('socket::dataQueue');
		$amqpChannel->queue_declare($amqpQueue, false, true, false, true);

		$consumer = new AMQPConsumer($amqpConnection, $amqpChannel, $amqpQueue, $reactLoop, 0.1, 100); // Poll every 0.1 seconds, retrieve up to 100 queue entries.
		$consumer->on('consume', [$wampHandler, 'onReceiveAmqp']);

		// Set up the session synchronization AMQP consumer, with its own dedicated queue.
		$syncQueue = \Config::get('socket::syncQueue');
		$amqpChannel->queue_declare($syncQueue, false, true, false, true);

		$consumer = new AMQPConsumer($amqpConnection, $amqpChannel, $syncQueue, $reactLoop, 0.1, 100); // Poll every 0.1 seconds, retrieve up to 100 queue entries.
		$consumer->on('consume', [$wampHandler, 'onReceiveSync']);

		// Sets up a web socket listener on the specified port.
		$webSocket = new \React\Socket\Server($reactLoop);
		$webSocket->listen($port, \Config::get('socket::serverBindAddress'));

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
