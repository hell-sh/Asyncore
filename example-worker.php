<?php
require "vendor/autoload.php";
use pas\pas;
$worker = pas::worker(__DIR__."/example-src/worker.php", function($data) use (&$worker)
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
pas::loop();
