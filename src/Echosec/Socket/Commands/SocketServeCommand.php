<?php namespace Echosec\Socket\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SocketServeCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'socket:serve';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Start the web socket server';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		$server = new \Echosec\Socket\Server\SocketServer();

		$this->info('Web socket server is now listening on port ' . $this->option('port'));

		$server->run($this->option('port'));
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return [
			['port', 'p', InputOption::VALUE_OPTIONAL, 'The local port that will receive connections', 8080],
		];
	}

}
