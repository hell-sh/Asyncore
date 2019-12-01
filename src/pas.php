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
	 * @return Loop
	 */
	static function add(callable $function, float $interval_seconds = 0.001, bool $call_immediately = false): Loop
	{
		return self::$conditions[0]->add($function, $interval_seconds, $call_immediately);
	}

	/**
	 * Registers a function to be called every X seconds if at least one other essential loop exists.
	 *
	 * @since 1.2
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
	 * Removes the given loop from the default Condition.
	 *
	 * @deprecated Use Loop::remove(), instead.
	 * @param Loop $loop
	 * @return void
	 */
	static function remove(Loop $loop): void
	{
		$loop->remove();
	}

	/**
	 * @deprecated Use pas::condition(), instead.
	 * @param callable $condition_function
	 * @return Condition
	 */
	static function whileLoop(callable $condition_function): Condition
	{
		return self::condition($condition_function);
	}

	/**
	 * Registers a Condition to contain loops until $condition_function returns false.
	 *
	 * @since 1.5
	 * @param callable $condition_function
	 * @return Condition
	 */
	static function condition(callable $condition_function): Condition
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
	 * @param callable|null $condition_function An optional function to determine when this function should return.
	 * @return void
	 */
	static function loop(?callable $condition_function = null): void
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
				$loops = self::$conditions[0]->loops;
				for($i = 2; $i < count(self::$conditions); $i++)
				{
					if(self::$conditions[$i]->isTrue())
					{
						$loops = array_merge($loops, self::$conditions[$i]->loops);
					}
					else
					{
						unset(self::$conditions[$i]);
					}
				}
				if(count($loops) == 0)
				{
					return;
				}
				$loops = array_merge($loops, self::$conditions[1]->loops);
				$shortest_loop = $loops[0]->interval_seconds;
				for($i = 1; $i < count($loops); $i++)
				{
					if($loops[$i]->interval_seconds < $shortest_loop)
					{
						$shortest_loop = $loops[$i]->interval_seconds;
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
						self::$recalculate_loops = true;
						continue 2;
					}
				}
			}
			$time = microtime(true);
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
		while(self::$loop_true && (!is_callable($condition_function) || $condition_function()));
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
		$loop = pas::add(function() use (&$loop, &$mh, &$ch, &$callback)
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
	 * Used internally to initialize pas's default Conditions.
	 */
	public static function init()
	{
		self::$conditions = [
			new AlwaysTrueCondition(),
			new AlwaysTrueCondition()
		];
	}
}

pas::init();
