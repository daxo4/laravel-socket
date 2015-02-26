<?php namespace Echosec\Socket\Server;

use Ratchet\Wamp\WampServerInterface;
use Ratchet\ConnectionInterface;

/**
Handles client interactions with the server via the WAMP interface.
*/
class WampHandler implements WampServerInterface {
	/**
	Implementation of ComponentInterface::onOpen().
	Executed when a client opens a connection.

	@param $connection The connection that has been opened.
	*/
	public function onOpen(ConnectionInterface $connection)
	{
		// WAMP handles the connection open, and we have no additional handling at this time.
	}

	/**
	Implementation of ComponentInterface::onClose().
	Executed when a client closes the connection.
	Depending on how Ratchet handles the close action, this may be called before or after the socket is closed.

	@param $connection The connection that has been closed.
	*/
	public function onClose(ConnectionInterface $connection)
	{
		// No additional handling required at this time.
	}

	/**
	Implementation of ComponentInterface::onError().
	Executed when an exception is thrown or an error occurs with one of the sockets.
	
	@param $connection The connection on which the error occurred.
	@param $e The exception that caused the error.
	@throws \Exception
	*/
	public function onError(ConnectionInterface $connection, \Exception $e)
	{
		// TODO
	}

	/**
	Implementation of WampServerInterface::onSubscribe().
	Executed when a client makes a request to subscribe to a topic.

	@param $connection The connection over which the request was made.
	@param $topic The topic that the client wishes to subscribe to.
	*/
	public function onSubscribe(ConnectionInterface $connection, $topic)
	{
		// TODO
	}

	/**
	Implementation of WampServerInterface::onUnSubscribe().
	Executed when a client makes a request to unsubscribe from a topic.

	@param $connection The connection over which the request was made.
	@param $topic The topic that the client wishes to unsubscribe from.
	*/
	public function onUnSubscribe(ConnectionInterface $connection, $topic)
	{
		// TODO
	}

	/**
	Implementation of WampServerInterface::onCall().
	Executed when an RPC call is received from a client.

	@param $connection The connection over which the call was received.
	@param $id The unique ID of the RPC. Used when generating responses.
	@param $topic The topic against which the call is executed.
	@param $params The parameters of the RPC call.
	*/
	public function onCall(ConnectionInterface $connection, $id, $topic, array $params)
	{
		// Users not allowed to make RPC calls via WAMP.
		$connection->callError($id, $topic, 'RPC calls not supported.')->close();
	}

	/**
	Implementation of WampServerInterface::onPublish().
	Executed when a request is made from a client to publish to other subscribed clients.

	@param $connection The connection over which the request was made.
	@param $topic The topic to which the message should be published.
	@param $event The payload to publish.
	@param $exclude A list of session IDs to exclude from the publication. (Blacklist)
	@param $eligible A list of session IDs to include in the publication. (Whitelist)
	*/
	public function onPublish(ConnectionInterface $connection, $topic, $event, array $exclude, array $eligible)
	{
		// Users not allowed to publish via pub/sub link.
		$connection->close(); // TODO Verify that WAMP isn't being automagical and pushing the event anyways.
	}

	/**
	Called when an AMQP message is received from the queue.

	@param $envelope A metadata wrapper around the message received.
	@param $queue The queue on which the message was received.
	*/
	public function onReceiveAmqp(AMQPEnvelope $envelope, AMQPQueue $queue = null)
	{
		$message = json_decode($envelope->getBody(), true);
		// TODO
	}
}
