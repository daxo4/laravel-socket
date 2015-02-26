<?php namespace Echosec\Socket\Server;

/**
Forwards messages from the message queue to the Javascript client.
*/
class SocketServerSender {
	public function serverReceiveAmqp(AMQPEnvelope $envelope, AMQPQueue $queue = null)
	{
		$message = $envelope->getBody();
		// TODO Dispatch to channel/users.
	}
}
