<?php namespace Echosec\Socket;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

class SocketServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Get an instance of AliasLoader
	 * 
	 * @return instance
	 */
	protected $aliasLoader;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('echosec\socket');

		// Register command on boot
		$this->commands('socket:serve');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// IoC Command
		$this->app->bind('socket:serve', function($app)
		{
			return new Commands\SocketServeCommand();
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}	

}
