<?php
namespace pas;
abstract class pas
{
	private static $event_handlers = [];
	/**
	 * @var $conditions Condition[]
	 */
	private static $conditions;
	public static $recalculate_loops = true;
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
	 * Registers a function to be called every X seconds.
	 *
	 * @param callable $function
	 * @param float $interval_seconds
	 * @param bool $call_immediately True if the function should be called immediately, false if the interval should expire first.
	 * @return int The id of the loop. Can be used to remove the loop using ::remove() later.
	 */
	static function add(callable $function, float $interval_seconds = 0.001, bool $call_immediately = false): int
	{
		return self::$conditions[0]->add($function, $interval_seconds, $call_immediately);
	}

	/**
	 * Removes the loop with the given id from the default Condition.
	 *
	 * @param int $id
	 * @return void
	 */
	static function remove(int $id): void
	{
		self::$conditions[0]->remove($id);
	}

	/**
	 * Registers a Condition.
	 *
	 * @param callable $condition_function The function that must return true for nested loops to run.
	 * @return Condition
	 */
	static function whileLoop(callable $condition_function): Condition
	{
		$condition = new Condition($condition_function);
		array_push(self::$conditions, $condition);
		self::$recalculate_loops = true;
		return $condition;
	}

	/**
	 * Causes the pas::loop() function to return, if it is currently running.
	 *
	 * @return void
	 */
	static function exitLoop(): void
	{
		self::$loop_true = false;
	}

	/**
	 * Runs pas's loop.
	 * This should be the last call in your script.
	 *
	 * @return void
	 */
	static function loop(): void
	{
		$loops = [];
		$shortest_loop = 0;
		self::$recalculate_loops = true;
		self::$loop_true = true;
		do
		{
			$start = microtime(true);
			if(self::$recalculate_loops)
			{
				$loops = [];
				foreach(self::$conditions as $i => $condition)
				{
					if(!$condition->isTrue())
					{
						unset(self::$conditions[$i]);
						continue;
					}
					$loops = array_merge($loops, $condition->loops);
				}
				if(count($loops) == 0)
				{
					return;
				}
				$shortest_loop = $loops[0]->interval_seconds;
				for($i = 1; $i < count($loops); $i++)
				{
					if($loops[$i]->interval_seconds < $shortest_loop)
					{
						$shortest_loop = $loops[$i]->interval_seconds;
					}
				}
				self::$recalculate_loops = false;
				$time = microtime(true);
			}
			else
			{
				$time = $start;
			}
			$on_time = true;
			foreach($loops as $loop)
			{
				if($loop->next_run < $time)
				{
					$loop->next_run += $loop->interval_seconds;
					$running_late = ($loop->next_run < $time);
					if($on_time && $running_late && $shortest_loop == $loop->interval_seconds)
					{
						$on_time = false;
					}
					($loop->function)($running_late);
				}
			}
			if($on_time && ($remaining = ($shortest_loop - (microtime(true) - $start))) > 0)
			{
				time_nanosleep(0, $remaining * 1000000000);
			}
		}
		while(self::$loop_true);
	}

	/**
	 * Drop-in replacement for `curl_exec`.
	 * Instead of blocking until the request has finished, this immediately returns and the result will be passed to the callback function when the request is finished.
	 *
	 * @param resource $ch
	 * @param callable $callback
	 */
	public static function curl_exec(&$ch, callable $callback): void
	{
		$mh = curl_multi_init();
		curl_multi_add_handle($mh, $ch);
		$i = pas::add(function() use (&$i, &$mh, &$ch, &$callback)
		{
			$active = 0;
			curl_multi_exec($mh, $active);
			if($active == 0)
			{
				$callback(curl_multi_getcontent($ch));
				curl_multi_remove_handle($mh, $ch);
				curl_multi_close($mh);
				pas::remove($i);
			}
		}, 0.005, true);
	}

	/**
	 * Used internally to initialize pas's default Condition.
	 */
	public static function init()
	{
		self::$conditions = [
			new AlwaysTrueCondition()
		];
	}
}

pas::init();
