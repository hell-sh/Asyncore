<?php
namespace pas;
class Condition
{
	private $condition_function;
	/**
	 * @var $loops Loop[]
	 */
	public $loops = [];

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
	 * @deprecated Use Loop::remove(), instead.
	 * @param Loop $loop
	 * @return void
	 */
	function remove(Loop $loop): void
	{
		$loop->remove();
	}
}
