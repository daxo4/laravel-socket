<?php namespace Echosec\Socket\Client;

/**
Allows a Laravel RESTful web server to push messages to the web socket server.
*/
class ClientPush {
	/**
	Pushes a message via web socket.

	@param string $message The message to send.
	@param string $channel The channel on which the message will be sent.
	@param array $users An array of users to send the message to. If empty, will send to all users.
	*/
	public function send($message, $channel = 'channel', array $users = array())
	{
		// TODO
	}

	/**
	Signal the web socket server that a user has logged on.
	*/
	public function login()
	{
		$this->sync('add');
	}	

	/**
	Signal the web socket server that a user is logging out.
	*/
	public function logout()
	{
		$this->sync('remove');
	}

	/**
	Pushes a request to synchronize a user's session and user IDs on the server.
	If the user authenticates via a RESTful endpoint on the LAMP server, we use this command to pass that authenticated session to the web socket server.
	*/
	private function sync(string $type) 
	{
		$sessionId = ''; // TODO
		$userId = ''; // TODO

		$messageArray = array(
			'sync'=>true,
			'type'=>$type,
			'sessionId'=>$sessionId,
			'userId'=>$userId
		);
		$message = json_encode($messageArray);
		$this->send($message, 'echosec.sync');
	}
}
