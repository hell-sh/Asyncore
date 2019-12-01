<?php
use pas\pas;
require "vendor/autoload.php";

echo "Please wait...\n";
pas::timeout(function() use (&$start)
{
	echo "Thanks for waiting!\n";
}, 1);
pas::loop();
