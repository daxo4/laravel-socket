<?php namespace Echosec\Socket\Server;

use PhpAmqpLib\Message\AMQPMessage as AMQPMessage;

use Ratchet\Wamp\WampServerInterface;
use Ratchet\ConnectionInterface;

/**
 * Handles client interactions with the server via the WAMP interface.
 */
class WampHandler implements WampServerInterface {
    /**
     * A lookup of topics that have been subscribed to.
     * Key: The topic 'id' (which is its name as a string)
     * Value: The Topic with that id.
     */
    protected $subscribedTopics = array();

    /**
     * A lookup of Ratchet-assigned session IDs and user IDs.
     * The user IDs are used for internal authentication checks.
     *
     * This mapping is one-to-many. A session can be associated with zero or one users,
     * but a user may have zero or many sessions. For instance, if a user opens multiple
     * browser windows, each with a separate web socket connection.
     *
     * Key: The web socket session id.
     * Value: The user id attached to that session, or null if the session does not have an associated user id.
     */
    protected $userSessionMap = array();

    /**
     * A cache of recently-received data, to be transmitted once a client (re-)connects to the server.
     */
    protected $dataCache = array();

    /**
     * Implementation of ComponentInterface::onOpen().
     * Executed when a client opens a connection.
     * @param $connection The connection that has been opened.
     */
    public function onOpen(ConnectionInterface $connection)
    {
        $this->userSessionMap[$connection->wrappedConn->WAMP->sessionId] = null;
    }

    /**
     * Implementation of ComponentInterface::onClose().
     * Executed when a client closes the connection.
     * Depending on how Ratchet handles the close action, this may be called before or after the socket is closed.
     * @param $connection The connection that has been closed.
     */
    public function onClose(ConnectionInterface $connection)
    {
        unset($this->userSessionMap[$connection->wrappedConn->WAMP->sessionId]);
    }

    /**
     * Implementation of ComponentInterface::onError().
     * Executed when an exception is thrown or an error occurs with one of the sockets.
     * @param $connection The connection on which the error occurred.
     * @param $e The exception that caused the error.
     * @throws \Exception
     */
    public function onError(ConnectionInterface $connection, \Exception $e)
    {
        // TODO
    }

    /**
     * Implementation of WampServerInterface::onSubscribe().
     * Executed when a client makes a request to subscribe to a topic.
     * @param $connection The connection over which the request was made.
     * @param $topic The topic that the client wishes to subscribe to.
    */
    public function onSubscribe(ConnectionInterface $connection, $topic)
    {
        // Add this topic to the list of subscribed topics.
        // TODO How does this work with multiple clients subscribing to a single topic?
        $this->subscribedTopics[$topic->getId()] = $topic;

        // Fetch any cached posts, broadcast them to the newly-subscribed client.
        $sessionId = $connection->WAMP->sessionId; 
        $this->transmitCache($sessionId, $topic->getId());
    }

    /**
     * Implementation of WampServerInterface::onUnSubscribe().
     * Executed when a client makes a request to unsubscribe from a topic.
     * @param $connection The connection over which the request was made.
     * @param $topic The topic that the client wishes to unsubscribe from.
    */
    public function onUnSubscribe(ConnectionInterface $connection, $topic)
    {
        // TODO Does not unset the subscribed topics list, since other clients may still be subscribed.
    }

    /**
     * Implementation of WampServerInterface::onCall().
     * Executed when an RPC call is received from a client.
     * @param $connection The connection over which the call was received.
     * @param $id The unique ID of the RPC. Used when generating responses.
     * @param $topic The topic against which the call is executed.
     * @param $params The parameters of the RPC call.
     */
    public function onCall(ConnectionInterface $connection, $id, $topic, array $params)
    {
        // Users not allowed to make RPC calls via WAMP.
        $connection->callError($id, $topic, 'RPC calls not supported.')->close();
    }

    /**
     * Implementation of WampServerInterface::onPublish().
     * Executed when a request is made from a client to publish to other subscribed clients.
     * @param $connection The connection over which the request was made.
     * @param $topic The topic to which the message should be published.
     * @param $event The payload to publish.
     * @param $exclude A list of session IDs to exclude from the publication. (Blacklist)
     * @param $eligible A list of session IDs to include in the publication. (Whitelist)
     */
    public function onPublish(ConnectionInterface $connection, $topic, $event, array $exclude, array $eligible)
    {
        // Users not allowed to publish via pub/sub link.
        $connection->close(); // Disconnect the misbehaving client.
    }

    /**
     * Called when an AMQP data message is received from the queue.
     * @param $envelope A metadata wrapper around the message received.
     */
    public function onReceiveAmqp(AMQPMessage $envelope)
    {
        // Extract and parse the received message.
        $message = json_decode($envelope->body, true);
        if (! array_key_exists('topic', $message) || ! array_key_exists('data', $message)) {
            return; // Ignore invalid messages.
        }
        $topicId = $message['topic'];
        $data = $message['data'];

        if (array_key_exists('auth', $message)) {
            // Fetch a list of users that pass the authentication permissions.
            $permissionHandler = \App::make('SocketPermissionInterface');
            $userWhitelist = $permissionHandler->getUserIds($topicId, $message, $message['auth']);
            $sessionWhitelist = array();
            foreach ($this->userSessionMap as $sessionId => $userId) {
                if (in_array($userId, $userWhitelist)) $sessionWhitelist[] = $sessionId;
            }

            // Add this post to the cache, with the associated whitelist of users.
            $this->addDataToCache($topicId, $data, $userWhitelist);
            
            if (count($sessionWhitelist) > 0) { // broadcast() treats empty array as a lack of restrictions, not a lack of acceptable users.
                if (array_key_exists($topicId, $this->subscribedTopics)) {
                    // There is one or more user subscribed to this topic. Broadcast the data.
                    $topic = $this->subscribedTopics[$topicId];
                    $topic->broadcast($data, array(), $sessionWhitelist);
                }
            }
        } else {
            // No authentication restrictions, broadcast to all users.
            $this->addDataToCache($topicId, $data);
            $topic->broadcast($data);
        }
    }

    /**
     * Called when an AMQP synchronization message is received from the queue.
     * Synchronization allows Laravel-socket to link a web socket session (created directly between the web socket server and a browser) to a user (which is managed jointly by Laravel and the browser).
     * This link can subsequently be used for authentication checks on messages sent through the web socket.
     * @param $envelope A metadata wrapper around the message received. This message should contain a 'sessionId', the web socket session ID, and a 'userId', a unique identifier of the user to which this session will be linked.
    */
    public function onReceiveSync(AMQPMessage $envelope)
    {
        // Parse the received message.
        $message = json_decode($envelope->body, true);
        if (! array_key_exists('sessionId', $message) || ! array_key_exists('userId', $message)) {
            return; // Ignore invalid messages.
        }
        $sessionId = $message['sessionId'];
        $userId = $message['userId'];

        // Act on the received message.
        if ($message['type'] == 'add') {
            if (array_key_exists($sessionId, $this->userSessionMap)) {
                // Add this user to the session mapping.
                $this->userSessionMap[$sessionId] = $userId;

                // Push any cached messages to all available topics.
                $this->transmitCache($sessionId);
            }
        } else if ($message['type'] == 'remove') {
            unset($this->userSessionMap[$sessionId]);
        }
    }

    /**
     * Adds data to a temporary cache for later retrieval.
     * @param $topicId The ID of the topic to which the data should be transmitted.
     * @param $data The data to cache.
     * @param array $userWhitelist A whitelist of user IDs that are permitted to access this cache. If this array is empty, ALL users have access (matching Ratchet's broadcast() functionality).
     */
    private function addDataToCache($topicId, $data, array $userWhitelist = array()) {
        $this->dataCache[] = array(
            'topicId' => $topicId,
            'data' => $data,
            'userWhitelist' => $userWhitelist,
            'timestamp' => time()
        );
        // TODO Not a great solution to the data cache consuming all our memory...
        if (count($this->dataCache) > 100) {
            array_shift($this->dataCache);
        }
    }

    /**
     * Attempts to broadcast cached data belonging to the specified cache.
     * This method makes no assumptions about the state of the connection; in particular, it may be called when the user has not synchronized with their socket, or when they haven't subscribed to the topic yet. It is valid to call transmitCache() during these states, but note that posts may not be submitted.
     * @param $sessionId The ID of the session to broadcast to.
     * @param String|null $topicId The ID of the topic on which to broadcast. If null (default), attempts to transmit to all topics.
     */
    private function transmitCache($sessionId, $topicId = null) {
        $userId = array_key_exists($sessionId, $this->userSessionMap) ? $this->userSessionMap[$sessionId] : null;
        foreach($this->dataCache as $cacheEntry) {
            // Check whether this cached message is destined for this topic.
            if (is_null($topicId) || $cacheEntry['topicId'] == $topicId) {
                // If there are permissions on this entry, check that associated user (if there is one) is permitted to access the data.
                if ((! is_null($userId) && in_array($userId, $cacheEntry['userWhitelist'])) || count($cacheEntry['userWhitelist']) == 0) {
                    // Make sure the topic has been subscribed to before fetching it.
                    if (array_key_exists($topicId, $this->subscribedTopics)) {
                        $topic = $this->subscribedTopics[$topicId];
                        // Broadcast to this specific session only, so other clients don't get duplicate messages when others sync.
                        $topic->broadcast($cacheEntry['data'], array(), array($sessionId));
                    }
                } 
            }
        }
    }

    /**
     * Clears old data from the data cache.
     */
    public function clearCache() {
        $time = time();
        foreach($this->dataCache as $cacheKey => $cacheValue) {
            if ($time - $cacheValue['timestamp'] > 5) {
                unset($this->dataCache[$cacheKey]);
            }
        }
    }
}
