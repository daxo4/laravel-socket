<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| AMQP Connection
	|--------------------------------------------------------------------------
	|
    | These settings are used by the RabbitMQ client library to connect to a 
    | RabbitMQ server.
	|
	*/

	'amqp' => array(
		'host'		=> 'localhost',
		'port'		=> 5672,
		'username'	=> 'guest',
		'password'	=> 'guest',
		'vhost'		=> '/'
	),

	/*
	|--------------------------------------------------------------------------
	| Web Socket Server Bind Address
	|--------------------------------------------------------------------------
	|
    | This is the IP address to which React's web socket library will listen.
	|
	*/

	'serverBindAddress' => '0.0.0.0',

	/*
	|--------------------------------------------------------------------------
	| AMQP Queue Names
	|--------------------------------------------------------------------------
	|
    | These settings control the names of queues to be used by AMQP for data transfer.
	|
	*/

    'dataQueue' => 'echosec.ws.queue',
    'syncQueue' => 'echosec.ws.sync',

);
