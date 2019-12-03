<?php
require "vendor/autoload.php";
use pas\
{pas, stdin};
pas::add(function()
{
	echo "1 second!\n";
}, 1);
pas::add(function()
{
	echo "3 seconds!\n";
}, 3);
stdin::init(function($line)
{
	echo "Got input: $line\n";
	if($line == "shutdown")
	{
		pas::exitLoop();
	}
});
echo "Starting loop. Type 'shutdown' to end it.\n";
pas::loop();
echo "Loop ended!\n";
