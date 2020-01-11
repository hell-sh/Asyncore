<?php
/**
 * The worker for ../example-worker.php
 */
require __DIR__."/../vendor/autoload.php";
use Asyncore\
{Asyncore, Master, Worker};
Worker::init(function($data)
{
	echo "Master -> Worker:\n";
	var_dump($data);
	assert(is_array($data));
	switch($data[0])
	{
		case "stop":
			exit;
		case "add":
			assert(count($data) == 3);
			Master::send($data[1] + $data[2]);
	}
});
asyncore::loop();
