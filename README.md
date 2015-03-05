# Laravel-socket
A simple web socket implementation in Laravel 4, using RabbitMQ for message passing.

# Features

* Server-to-client push notifications.
* Use of RabbitMQ for inter-process communication.
* Secure, private communication.

At the moment, this library only supports server-to-client interactions. We plan in the future to add client-to-client and client-to-server interactions as well.

# Security
Laravel-socket allows for secure, confidential messages to authenticated users.
Laravel-socket does **not** provide authentication itself; instead, it allows authentication to be handled by your REST server, with permission checks provided by you.

Laravel-socket's security is based on a two-sided agreement. A client requests to receive data from the server by subscribing to a topic, while the server chooses which clients can receive data by checking user permissions.

This means that Laravel-socket's permission system is **not** turnkey, and thus is **disabled by default**. To enable security, follow the optional steps outlined during installation.

# Installation and configuration
#### Installation via Composer
Add the following lines to your project's composer.json:

```json
"require": {
	...
	"echosec/laravel-socket": "dev-master"
}
```

#### Configuration files
Register the Laravel-socket service provider in your project's `app/config/app.php`, under `providers`.
```php
'providers' => array(
	...
	'Echosec\Socket\SocketServiceProvider',
),
```
If you want to generate push notifications from the server, additionally add the ClientPush facade to your `aliases` array in the same file:
```php
'aliases' => array(
	...
	'Echosec\Socket\Facades\ClientPush',
),
```
# Using Laravel-socket
#### Run the server
As a web socket service, Laravel-socket must be run alongside a conventional REST server.

You can run the server from the command line via Artisan:
```
php artisan socket:serve [--port=8080]
```

TODO Instructions on how to run SocketServer.php directly as a React server.

TODO Instructions and testing on running multiple SocketServer instances behind a load balancer.

#### Browser connection via Javascript
In order to receive messages, you must open a web socket connection, then subscribe to one or more topics.

We recommend the use of [Autobahn](http://autobahn.ws/). Javascript sample taken from [Ratchet's WAMP tutorial](http://socketo.me/docs/push), which is good reading in general for the structure of web socket push notifications.
```javascript
console.log('Initializing web socket');
var conn = new ab.Session('ws://127.0.0.1:8080',
	function() {
		console.log('Web socket session id: ' + conn.sessionid());
		conn.subscribe('post.kittens', function(topic, data) {
			// This is where you would handle the message.
			console.log('New article published to category "' + topic + '" : ' + data);
		});
	},
	function() {
		console.warn('WebSocket connection closed');
	},
	{'skipSubprotocolCheck': true}
);
console.log('Initialized web socket');
```

#### Send messages
There are two ways to send messages to Laravel-socket. You can use the built-in 'ClientPush' facade from your REST controllers, or you can create RabbitMQ messages manually from any process with a RabbitMQ connection.

##### Using ClientPush
You may use the helper class ClientPush, either via Laravel facades or directly through the PHP class, to send messages to the web socket.

If you added the ClientPush facade to your `aliases` (see above), then you can just call the class directly.
```php
$topic = 'post.kittens';
$data = 'Kittens are awesome!';
ClientPush::send($topic, $data);
```

##### Using RabbitMQ
If you want to send push notifications from non-PHP processes, you can format your own RabbitMQ messages. The body of the message should be a JSON-encoded object of the following format:
```json
{
	"topic": "post.kittens",
	"data": "Kittens are awesome!"
}
```
Optionally, you may add an "auth" field (see the section on permission checks below).

Your message should be sent to the queue specified in Laravel-socket's configuration file, which defaults to "echosec.ws.queue".

You probably shouldn't be using non-PHP processes to send account synchronization messages. If you really, really need to, the message format and queue can be found in the ClientPush source code.

#### Optional: Configure permission checks
Laravel-socket allows for a customized permission system to be assigned to messages. This allows you to choose what clients will receive each message.

1. Add a REST endpoint that calls `ClientPush::login()`. This function takes two parameters. The first is the web socket session id, which should be passed from the client after they establish a web socket connection. The second, the user id, is a string that uniquely identifies a user in your system. This call will associate this user id with the web socket session.
```php
class WebSocketController extends BaseController {
	public function syncWebSocket() {
		$sessionId = Input::get('session_id');
		$userId = Auth::user()->id;
		ClientPush::login($sessionId, $userId);
		return "Synchronize request received.";
	}
}
```
2. Create a permission handler by extending `Echosec\Socket\Server\SocketPermissionInterface`. This interface has a single function, `getUserIds($topic, $message, $auth)`, which returns an array of user ids that are permitted to view the specified message. The following is a trivial implementation, to show the expected return format.
```php
<?php

use Echosec\Socket\Server\SocketServerInterface;

class PermissionHandler implements SocketServerInterface
{
        public function getUserIds($topic, $data, $auth) 
        {
                // Implement your handling here!
                return array('alice', 'bob');
        }
}

```
  * These must be the same user ids that you provided to `ClientPush::login()`.
3. Bind this permission handler in your IOC container, so Laravel-socket can access it.
```
App::bind('SocketPermissionInterface', 'PermissionHandler');
```
  * TODO Implement an alternative system that doesn't use Laravel's IOC functionality, so the permission system can be bound in non-Laravel environments.
4. When sending messages via `ClientPush::send()`, provide a non-null string for the third parameter, `$auth`.
  * This `$auth` string will be provided directly to the permission handler's `getUserIds()`, and otherwise serves no purpose. You may use this string to store special instructions for your permission handler, if you wish.
  * If `$auth` is null or omitted when sending a message, then permission checks will be **skipped** for this message.

When a message with a non-null `$auth` field is received by the server, a call will be made to `getUserIds()`, which should return an array of user ids. The message will only be broadcast to web socket sessions associated with a user in this array.

**WARNING**: The SocketPermissionInterface will run on the web socket server. State saved on the REST server will **not** be available to the SocketPermissionInterface. In particular, session information (including that in PHP's `session` or Laravel's `Session` facade) will not be available during the call to `getUserIds`.

If you need Laravel session information, look into Laravel's `Illuminate\Session\Store`. The web socket server is potentially shared among multiple concurrent connections, so you should **not** store stateful information specific to a single message or connection.

# Troubleshooting
#### ReflectionException - class SocketPermissionInterface does not exist.
This means you sent a message with an 'auth' field, but have not configured a class to handle permission checks. Make sure the class exists, and that you've configured the IoC binding to expose it to Laravel-socket.

# FAQ
#### Why [RabbitMQ](https://www.rabbitmq.com/)? Why not [ZeroMQ](http://zeromq.org/), like other React-based web sockets?
Short answer: because our backend already uses RabbitMQ for inter-process communication, and we didn't want two queueing systems.

Long answer: we wanted a single messaging system to support our entire platform. One of the beautiful things about using a queueing system for inter-process communication is the ability for non-PHP applications to enter the network. The rest of our tech uses RabbitMQ for message passing, and so using RabbitMQ for push notifications kept our dependencies low.

ZeroMQ has some advantages over RabbitMQ [see here](http://code.hootsuite.com/why-we-love-and-use-zeromq-at-hootsuite/). Ultimately, if you're willing to invest the time and resources into it, ZeroMQ will ultimately be a more powerful and efficient system. That said, RabbitMQ is more useful 'out of the box'. 

Ultimately, the system is not completely married to RabbitMQ as a message passing system. We use a [custom variant of the ReactAMQP library](https://github.com/echosec/ReactAMQP) (using php-amqplib instead of the PECL extensions), and it would be reasonably easy to substitute in [React ZMQ](https://github.com/reactphp/zmq) instead.

# License
The MIT License (MIT)

Copyright (c) 2015 echosec

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
