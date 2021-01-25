<?php
require __DIR__."/../vendor/autoload.php";
use Asyncore\
{Asyncore, stdin};
Asyncore::add(function()
{
	echo "1 second!\n";
}, 1);
Asyncore::add(function()
{
	echo "3 seconds!\n";
}, 3);
stdin::init(function($line)
{
	echo "Got input: $line\n";
	if($line == "shutdown")
	{
		Asyncore::exitLoop();
	}
});
echo "Starting loop. Type 'shutdown' to end it.\n";
Asyncore::loop();
echo "Loop ended!\n";
