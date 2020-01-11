<?php
namespace Asyncore;
use RuntimeException;
abstract class Asyncore
{
	static $recalculate_loops = true;
	/**
	 * @var $conditions Condition[]
	 */
	static $conditions;
	private static $event_handlers = [];
	private static $loop_true = true;

	/**
	 * Returns true if the code is running on a Windows machine.
	 *
	 * @return boolean
	 */
	static function isWindows(): bool
	{
		return defined("PHP_WINDOWS_VERSION_MAJOR");
	}

	/**
	 * Registers an event handler.
	 *
	 * @param string $event
	 * @param callable $function
	 * @return void
	 */
	static function on(string $event, callable $function): void
	{
		if(array_key_exists($event, self::$event_handlers))
		{
			array_push(self::$event_handlers[$event], $function);
		}
		else
		{
			self::$event_handlers[$event] = [$function];
		}
	}

	/**
	 * Calls all event handlers for the given event.
	 *
	 * @param string $event
	 * @param array $parameters
	 * @return void
	 */
	static function fire(string $event, array $parameters = []): void
	{
		if(array_key_exists($event, self::$event_handlers))
		{
			foreach(self::$event_handlers[$event] as $function)
			{
				call_user_func_array($function, $parameters);
			}
		}
	}

	/**
	 * Registers a function to be called every X seconds if at least one other essential loop exists.
	 *
	 * @param callable $function
	 * @param float $interval_seconds
	 * @param bool $call_immediately True if the function should be called immediately, false if the interval should expire first.
	 * @return Loop
	 */
	static function addInessential(callable $function, float $interval_seconds = 0.001, bool $call_immediately = false): Loop
	{
		return self::$conditions[1]->add($function, $interval_seconds, $call_immediately);
	}

	/**
	 * Registers a Condition to contain loops until $condition_function returns false.
	 *
	 * @param callable $condition_function
	 * @return Condition
	 */
	static function condition(callable $condition_function): Condition
	{
		return new Condition($condition_function);
	}

	/**
	 * Causes the Asyncore::loop() function to return, if it is currently running.
	 *
	 * @return void
	 */
	static function exitLoop(): void
	{
		self::$loop_true = false;
	}

	/**
	 * Runs Asyncore's loop.
	 * This should be the last call in your script.
	 *
	 * @param callable|null $condition_function An optional function to determine when this function should return.
	 * @return void
	 */
	static function loop(?callable $condition_function = null): void
	{
		$loops = [];
		$shortest_loop_interval_seconds = 0;
		self::$recalculate_loops = true;
		self::$loop_true = true;
		do
		{
			$start = microtime(true);
			if(self::$recalculate_loops)
			{
				$loops = self::$conditions[0]->loops;
				for($i = 2; $i < count(self::$conditions); $i++)
				{
					if(self::$conditions[$i]->isTrue())
					{
						$loops = array_merge($loops, self::$conditions[$i]->loops);
					}
					else
					{
						foreach(self::$conditions[$i]->false_handlers as $handler)
						{
							$handler();
						}
						unset(self::$conditions[$i]);
					}
				}
				if(count($loops) == 0)
				{
					return;
				}
				$loops = array_merge($loops, self::$conditions[1]->loops);
				$shortest_loop_interval_seconds = $loops[0]->interval_seconds;
				for($i = 1; $i < count($loops); $i++)
				{
					if($loops[$i]->interval_seconds < $shortest_loop_interval_seconds)
					{
						$shortest_loop_interval_seconds = $loops[$i]->interval_seconds;
					}
				}
				self::$recalculate_loops = false;
			}
			else
			{
				for($i = 2; $i < count(self::$conditions); $i++)
				{
					if(!self::$conditions[$i]->isTrue())
					{
						foreach(self::$conditions[$i]->false_handlers as $handler)
						{
							$handler();
						}
						unset(self::$conditions[$i]);
						self::$recalculate_loops = true;
						continue 2;
					}
				}
			}
			$time = microtime(true);
			$on_time = true;
			$shortest_loop_next_run = microtime(true) + $shortest_loop_interval_seconds;
			foreach($loops as $loop)
			{
				if($loop->next_run <= $time)
				{
					$loop->next_run += $loop->interval_seconds;
					$running_late = ($loop->next_run < $time);
					if($shortest_loop_interval_seconds == $loop->interval_seconds)
					{
						if($on_time && $running_late)
						{
							$on_time = false;
						}
						else if($shortest_loop_next_run < $loop->next_run)
						{
							$shortest_loop_next_run = $loop->next_run;
						}
					}
					($loop->function)($running_late);
				}
			}
			if($on_time && (($remaining = $shortest_loop_next_run - $start) > 0))
			{
				time_nanosleep(floor($remaining), intval(($remaining - floor($remaining)) * 1000000000));
			}
		}
		while(self::$loop_true && (!is_callable($condition_function) || $condition_function()));
	}

	/**
	 * Calls the given function in x seconds.
	 *
	 * @param callable $callback
	 * @param float $seconds
	 * @return void
	 */
	static function timeout(callable $callback, float $seconds): void
	{
		$loop = self::add(function() use (&$callback, &$loop)
		{
			$loop->remove();
			$callback();
		}, $seconds, false);
	}

	/**
	 * Registers a function to be called every X seconds.
	 *
	 * @param callable $function
	 * @param float $interval_seconds
	 * @param bool $call_immediately True if the function should be called immediately, false if the interval should expire first.
	 * @param bool $essential False if the function should only be called if at least one other essential function exists.
	 * @return Loop
	 */
	static function add(callable $function, float $interval_seconds = 0.001, bool $call_immediately = false, bool $essential = true): Loop
	{
		return self::$conditions[$essential ? 0 : 1]->add($function, $interval_seconds, $call_immediately);
	}

	/**
	 * Creates a new PHP thread running the given file.
	 *
	 * @param string $worker_file The absolute path to the worker's php file.
	 * @param callable $message_handler The function to be called when the worker sends a message.
	 * @return Worker
	 */
	static function worker(string $worker_file, callable $message_handler): Worker
	{
		$proc = proc_open("php \"".realpath($worker_file)."\"", [
			0 => [
				"pipe",
				"r"
			],
			1 => STDOUT,
			2 => [
				"pipe",
				"w"
			]
		], $pipes);
		if(!is_resource($proc))
		{
			throw new RuntimeException("Failed to worker process");
		}
		return new Worker($proc, $pipes, $message_handler);
	}

	/**
	 * Drop-in replacement for `curl_exec`.
	 * Instead of blocking until the request has finished, this immediately returns and the result will be passed to the callback function when the request is finished.
	 *
	 * @param resource $ch
	 * @param callable $callback
	 * @return void
	 */
	static function curl_exec(&$ch, callable $callback): void
	{
		$mh = curl_multi_init();
		curl_multi_add_handle($mh, $ch);
		$loop = self::add(function() use (&$loop, &$mh, &$ch, &$callback)
		{
			$active = 0;
			curl_multi_exec($mh, $active);
			if($active == 0)
			{
				$loop->remove();
				curl_multi_remove_handle($mh, $ch);
				curl_multi_close($mh);
				$callback(curl_multi_getcontent($ch));
			}
		}, 0.001, true);
	}

	/**
	 * Used internally to initialize Asyncore's default Conditions.
	 */
	static function init()
	{
		self::$conditions = [];
		new Condition(function()
		{
		});
		new Condition(function()
		{
		});
	}
}

Asyncore::init();
