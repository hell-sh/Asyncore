<?php
namespace pas;
class Condition
{
	/**
	 * @var $loops Loop[]
	 */
	public $loops = [];
	/**
	 * @var $false_handlers callable[]
	 * @since 1.6
	 */
	public $false_handlers = [];
	private $condition_function;

	/**
	 * @param callable $condition_function
	 * @see pas::condition()
	 */
	function __construct(callable $condition_function)
	{
		$this->condition_function = $condition_function;
	}

	function isTrue(): bool
	{
		return ($this->condition_function)() === true;
	}

	/**
	 * @param callable $handler
	 * @return Condition $this
	 * @since 1.6
	 */
	function onFalse(callable $handler): Condition
	{
		array_push($this->false_handlers, $handler);
		return $this;
	}

	/**
	 * Registers a function to be called every X seconds.
	 *
	 * @param callable $function
	 * @param float $interval_seconds
	 * @param bool $call_immediately True if the function should be called immediately, false if the interval should expire first.
	 * @return Loop
	 */
	function add(callable $function, float $interval_seconds = 0.001, bool $call_immediately = false): Loop
	{
		$loop = new Loop($this, $function, $interval_seconds, $call_immediately);
		array_push($this->loops, $loop);
		pas::$recalculate_loops = true;
		return $loop;
	}

	/**
	 * Removes the given loop.
	 *
	 * @param Loop $loop
	 * @return Condition $this
	 * @deprecated Use Loop::remove(), instead.
	 */
	function remove(Loop $loop): Condition
	{
		$loop->remove();
		return $this;
	}
}
