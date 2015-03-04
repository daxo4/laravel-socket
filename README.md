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

#### Run the server
You can run the server from the command line via Artisan:
```
php artisan socket:serve [--port=8080]
```

TODO Instructions on how to run SocketServer.php directly as a React server.

TODO Instructions and testing on running multiple SocketServer instances behind a load balancer.

#### Optional: Configure permission checks
Laravel-socket allows for a customized permission system to be assigned to messages. This allows you to choose what clients will receive each message.

1. Add a REST endpoint that calls `ClientPush::login()`. This function takes two parameters. The first is the web socket session id, which should be passed from the client after they establish a web socket connection. The second, the user id, is a string that uniquely identifies a user in your system. This call will associate this user id with the web socket session.
```
class WebSocketController extends BaseController {
	public function syncWebSocket() {
		$sessionId = Input::get('session_id');
		$userId = Auth::user()->id;
		ClientPush::login($sessionId, $userId);
		return "Synchronize request received.";
	}
}
```
2. Create a permission handler by extending `Echosec\Socket\Server\SocketPermissionInterface`. This interface has a single function, `getUserIds($topic, $message, $auth)`, which returns an array of user ids that are permitted to view the specified message.
```
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
	a. These must be the same user ids that you provided to `ClientPush::login()`.
3. Bind this permission handler in your IOC container, so Laravel-socket can access it.
```
App::bind('SocketPermissionInterface', 'PermissionHandler');
```
	b. TODO Implement an alternative system that doesn't use Laravel's IOC functionality, so the permission system can be bound in non-Laravel environments.
4. When sending messages via `ClientPush::send()`, provide a non-null string for the third parameter, `$auth`.
	a. This `$auth` string will be provided directly to the permission handler's `getUserIds()`, and otherwise serves no purpose. You may use this string to store special instructions for your permission handler, if you wish.
	b. If `$auth` is null or omitted when sending a message, then permission checks will be **skipped** for this message.

When a message with a non-null `$auth` field is received by the server, a call will be made to `getUserIds()`, which should return an array of user ids. The message will only be broadcast to web socket sessions associated with a user in this array.

**WARNING**: The SocketPermissionInterface will run on the web socket server. State saved on the REST server will **not** be available to the SocketPermissionInterface. In particular, session information (including that in PHP's `session` or Laravel's `Session` facade) will not be available during the call to `getUserIds`.

If you need Laravel session information, look into Laravel's `Illuminate\Session\Store`. The web socket server is potentially shared among multiple concurrent connections, so you should **not** store stateful information specific to a single message or connection.

# Troubleshooting
#### ReflectionException - class SocketPermissionInterface does not exist.
This means you sent a message with an 'auth' field, but have not configured a class to handle permission checks.

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
