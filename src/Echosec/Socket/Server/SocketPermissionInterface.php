<?php namespace Echosec\Socket\Server;

/**
Implements a custom handler for permission checks.
*/
interface SocketPermissionInterface
{
	/**
	Retrieves a list of user ids that are allowed to access the specified message.

	Warning: This call will be SKIPPED for messages that lack an 'auth' field. Even if your check only uses the topic or message fields, you must still supply an 'auth' field, or permission checks will be skipped and the message will be broadcast to all subscribed users!

	@param string $topic The topic to which the message is being sent.
	@param string $message The message that is being broadcast.
	@param string $auth The authentication details attached to the message.

	@return array An array of zero or more user ids that are allowed to receive the specified message.
	*/
	public function getUserIds($topic, $message, $auth);
}
