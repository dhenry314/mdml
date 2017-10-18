#!/usr/bin/php
<?php

require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';

if(count($argv)<2) {
	die("USAGE: HelloWorld.php {yourName}");
}
$yourName = $argv[1];
if(!file_exists('_TOKEN')) die("No _TOKEN file found.  Please run getToken.php. \n");
$jwt = file_get_contents('_TOKEN');

$url = "http://localhost".$config['BASE_PATH']."serviceCall";

$client = new \mdml\ServiceClient($jwt);

$request = '{
 	"type": "jsonwsp/request",
	"version": "1.0",
    	"methodname": "HelloWorld",
    	"args": {
    		"yourName":"'.$yourName.'"
    	},
    	"mirror": {
        	"id": 1
    	}
}';
$result = $client->post($request,$url);
echo print_r($result);

?>
