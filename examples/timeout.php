<?php
require __DIR__."/../vendor/autoload.php";
use Asyncore\Asyncore;
echo "Please wait...\n";
Asyncore::timeout(function() use (&$start)
{
	echo "Thanks for waiting!\n";
}, 1);
Asyncore::loop();
