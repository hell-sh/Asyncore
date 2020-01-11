<?php
namespace Asyncore;
/**
 * The class used by worker scripts to interact with their "master."
 *
 * @see Worker
 */
abstract class Master
{
	/**
	 * Initiates the worker script and registers a message handler.
	 *
	 * @param callable $message_handler The function to be called when the master sends a message.
	 * @return void
	 */
	static function init(callable $message_handler): void
	{
		Worker::init($message_handler);
	}

	/**
	 * Sends an object to the master.
	 *
	 * @param $data
	 * @return void
	 */
	static function send($data): void
	{
		fwrite(STDERR, "\0".serialize($data)."\0");
	}
}
