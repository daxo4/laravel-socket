<?php namespace Echosec\Socket\Facades;

use Illuminate\Support\Facades\Facade;

class ClientPush extends Facade
{
	protected static function getFacadeAccessor()
	{
		return 'client_push';
	}
}
