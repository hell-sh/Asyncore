<?php
require __DIR__."/../vendor/autoload.php";
use Asyncore\Asyncore;
$ch = curl_init("http://ip.apimon.de/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
echo "Sending request...";
Asyncore::add(function()
{
	echo ".";
}, 0.02);
Asyncore::curl_exec($ch, function($res)
{
	echo "\nYour IP address: $res\n";
	Asyncore::exitLoop();
});
Asyncore::loop();
