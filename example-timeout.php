<?php
use Asyncore\Asyncore;
require "vendor/autoload.php";
echo "Please wait...\n";
Asyncore::timeout(function() use (&$start)
{
	echo "Thanks for waiting!\n";
}, 1);
Asyncore::loop();
