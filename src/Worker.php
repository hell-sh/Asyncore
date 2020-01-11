<?php
namespace Asyncore;
/**
 * @see Master
 */
class Worker
{
	/**
	 * The Condition that is true as long as the Worker is running.
	 *
	 * @var Condition $running_condition
	 */
	public $running_condition;
	private $proc;
	private $pipes;
	private $message_handler;

	/**
	 * @param resource $proc
	 * @param array $pipes
	 * @param callable $message_handler
	 * @see Asyncore::worker
	 */
	function __construct(&$proc, array &$pipes, callable $message_handler)
	{
		$this->proc = $proc;
		stream_set_blocking($pipes[0], false);
		stream_set_blocking($pipes[2], false);
		$this->pipes = $pipes;
		$this->message_handler = $message_handler;
		$this->running_condition = new Condition(function()
		{
			return $this->isRunning();
		});
		$this->running_condition->add(function()
		{
			$this->evaluateProcStderr();
		}, 0.05);
		$this->running_condition->onFalse(function()
		{
			$this->evaluateProcStderr();
		});
	}

	function isRunning(): bool
	{
		return proc_get_status($this->proc)["running"];
	}

	private function evaluateProcStderr(): void
	{
		self::evaluatePipe($this->pipes[2], $this->message_handler);
	}

	private static function evaluatePipe($pipe, callable &$message_handler): void
	{
		$data = "";
		while($message = fread($pipe, 4096))
		{
			do
			{
				if($data)
				{
					$data_end = strpos($message, "\0");
					if($data_end === false)
					{
						$data .= $message;
						break;
					}
					$data = substr($message, 0, $data_end);
				}
				else
				{
					$data_start = strpos($message, "\0");
					if($data_start === false)
					{
						fwrite(STDERR, $message);
						break;
					}
					$data_end = strpos($message, "\0", $data_start + 1);
					if($data_end === false)
					{
						fwrite(STDERR, substr($message, 0, $data_start));
						$data = substr($message, $data_start + 1);
						break;
					}
					fwrite(STDERR, substr($message, 0, $data_start));
					$data = substr($message, $data_start + 1, $data_end);
				}
				$message = substr($message, $data_end + 1);
				$message_handler(unserialize($data));
				$data = "";
			}
			while($message);
		}
	}

	/**
	 * Initiates the worker script and registers a message handler.
	 *
	 * @param callable $message_handler The function to be called when the master sends a message.
	 * @return void
	 */
	static function init(callable $message_handler): void
	{
		stream_set_blocking(STDIN, false);
		Asyncore::add(function() use (&$message_handler)
		{
			self::evaluatePipe(STDIN, $message_handler);
		}, 0.001);
	}

	/**
	 * Sends an object to the worker.
	 *
	 * @param $data
	 * @return Worker $this
	 */
	function send($data): Worker
	{
		fwrite($this->pipes[0], "\0".serialize($data)."\0");
		return $this;
	}
}
