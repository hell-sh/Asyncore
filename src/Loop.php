<?php
namespace pas;
class Loop
{
	public $function;
	public $interval_seconds;
	public $next_run;

	/**
	 * @param callable $function
	 * @param float $interval_seconds
	 * @param bool $start_immediately
	 * @see pas::add()
	 * @see Condition::add()
	 */
	function __construct(callable $function, float $interval_seconds, bool $start_immediately)
	{
		$this->function = $function;
		$this->interval_seconds = $interval_seconds;
		$this->next_run = microtime(true);
		if(!$start_immediately)
		{
			$this->next_run += $interval_seconds;
		}
	}
}
