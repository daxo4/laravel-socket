<?php namespace Echosec\Socket\Server;

/**
Handles input to the web server via AMQP, 
which allows other processes to communicate with and push data to the web socket server.
*/
class AmqpHandler {
	/**
	Called when the server receives an AMQP message.
	*/
	public function receiveAmqp(AMQPEnvelope $envelope, AMQPQueue $queue = null)
	{
		$message = $envelope->getBody();
		// TODO
	}
}
