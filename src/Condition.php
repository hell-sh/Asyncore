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
	 * @see pas::whileLoop()
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
	 * @return int The id of the loop. Can be used to remove the loop using ::remove() later.
	 */
	function add(callable $function, float $interval_seconds = 0.001, bool $call_immediately = false): int
	{
		$loop = new Loop($this, $function, $interval_seconds, $call_immediately);
		array_push($this->loops, $loop);
		pas::$recalculate_loops = true;
		return array_search($loop, $this->loops);
	}

	/**
	 * Removes the loop with the given id.
	 *
	 * @param int $id
	 * @return void
	 */
	function remove(int $id): void
	{
		unset($this->loops[$id]);
		pas::$recalculate_loops = true;
	}
}
