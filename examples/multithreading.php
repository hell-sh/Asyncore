<?php
require __DIR__."/../vendor/autoload.php";
use Asyncore\Asyncore;
$worker = Asyncore::worker(__DIR__."/multithreading-worker.php", function($data) use (&$worker)
{
	echo "Worker -> Master:\n";
	var_dump($data);
	$worker->send(["stop"]);
});
$worker->send([
	"add",
	1,
	2
]);
Asyncore::loop();
