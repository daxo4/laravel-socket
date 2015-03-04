<?php namespace Echosec\Socket\Server;

use PhpAmqpLib\Message\AMQPMessage as AMQPMessage;

use Ratchet\Wamp\WampServerInterface;
use Ratchet\ConnectionInterface;

/**
Handles client interactions with the server via the WAMP interface.
*/
class WampHandler implements WampServerInterface {
	/**
	A lookup of topics that have been subscribed to.
	Key: The topic 'id' (which is its name as a string)
	Value: The Topic with that id.
	*/
	protected $subscribedTopics = array();

	/**
	A lookup of Ratchet-assigned session IDs and user IDs.
	The user IDs are used for internal authentication checks.

	This mapping is one-to-many. A session can be associated with zero or one users,
	but a user may have zero or many sessions. For instance, if a user opens multiple
	browser windows, each with a separate web socket connection.

	Key: The web socket session id.
	Value: The user id attached to that session, or null if the session does not have an associated user id.
	*/
	protected $userSessionMap = array();

	/**
	Implementation of ComponentInterface::onOpen().
	Executed when a client opens a connection.

	@param $connection The connection that has been opened.
	*/
	public function onOpen(ConnectionInterface $connection)
	{
		$this->userSessionMap[$connection->WAMP->sessionId] = null;
	}

	/**
	Implementation of ComponentInterface::onClose().
	Executed when a client closes the connection.
	Depending on how Ratchet handles the close action, this may be called before or after the socket is closed.

	@param $connection The connection that has been closed.
	*/
	public function onClose(ConnectionInterface $connection)
	{
		unset($this->userSessionMap[$connection->WAMP->sessionId]);
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
		// Add this topic to the list of subscribed topics.
		$this->subscribedTopics[$topic->getId()] = $topic;
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
		$connection->close(); // Disconnect the misbehaving client.
	}

	/**
	Called when an AMQP data message is received from the queue.

	@param $envelope A metadata wrapper around the message received.
	*/
	public function onReceiveAmqp(AMQPMessage $envelope)
	{
		$message = json_decode($envelope->body, true);
		if (! array_key_exists('topic', $message) || ! array_key_exists('data', $message)) {
			return; // Ignore invalid messages.
		}
		$topicId = $message['topic'];
		$data = $message['data'];

		if (! array_key_exists($topicId, $this->subscribedTopics)) {
			return; // If topic does not exist, ignore this publication.
		}
		$topic = $this->subscribedTopics[$topicId];

		$topic->broadcast($data); // TODO Handle user restrictions.
	}

	/**
	Called when an AMQP synchronization message is received from the queue.

	@param $envelope A metadata wrapper around the message received.
	*/
	public function onReceiveSync(AMQPMessage $envelope)
	{
		$message = json_decode($envelope->body, true);
		if (! array_key_exists('sessionId', $message) || ! array_key_exists('userId', $message)) {
			return; // Ignore invalid messages.
		}
		$sessionId = $message['sessionId'];
		$userId = $message['userId'];

		if ($message['type'] == 'add') {
			if (array_key_exists($sessionId, $this->userSessionMap)) {
				$this->userSessionMap[$sessionId] = $userId;
			}
		} else if ($message['type'] == 'remove') {
			unset($this->userSessionMap[$sessionId]);
		}
	}
}
