<?php
require "vendor/autoload.php";
use pas\pas;

$ch = curl_init("http://ip.apimon.de/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
echo "Sending request...";
pas::add(function()
{
	echo ".";
}, 0.02);
pas::curl_exec($ch, function($res)
{
	echo "\nYour IP address: $res\n";
	pas::exitLoop();
});
pas::loop();
