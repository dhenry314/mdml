#!/usr/bin/php
<?php

require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';
$auth = $config['auth'];
if(array_key_exists('username',$auth)
    && array_key_exists('password',$auth)
    && array_key_exists('loginService',$auth))
    {
       $fullLoginUrl = $auth['loginService']."?username=".$auth['username']."&password=".$auth['password'];
       if(!$authContents = \mdml\Utils::getFileContents($fullLoginUrl)) {
              die("No login url found at " . $fullLoginUrl);
       }
       $authResult = \mdml\Utils::jsonToObj($authContents);
       $jwt = $authResult['JWT'];
	if(file_put_contents("_TOKEN",$jwt)) {
		die("Wrote JWT token to _TOKEN");
	} else {
		die("Could not write to _TOKEN. \n JWT = " . $jwt . "\n\n");
	}
    }
die("Auth not configured!\n");

?>
