<?php
namespace pas;
/**
 * The class used by worker scripts to interact with their "master."
 *
 * @see Worker
 * @since 1.6
 */
abstract class Master
{
	/**
	 * Initiates the worker script and registers a message handler.
	 *
	 * @param callable $message_handler The function to be called when the master sends a message.
	 * @return void
	 */
	public static function init(callable $message_handler): void
	{
		stream_set_blocking(STDIN, false);
		pas::add(function() use (&$message_handler)
		{
			Worker::evaluatePipe(STDIN, $message_handler);
		}, 0.001);
	}

	/**
	 * Sends an object to the master.
	 *
	 * @param $data
	 * @return void
	 */
	public static function send($data): void
	{
		fwrite(STDERR, "\0".serialize($data)."\0");
	}
}
