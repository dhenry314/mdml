#!/usr/bin/php
<?php
set_time_limit(0);
require __DIR__ . '/../vendor/autoload.php';
if(!file_exists(__DIR__.'/../config.php')) {
        die("No config.php found in this directory.  \n
        Please copy config.example.php to config.php and change the settings as appropriate.\n
        Then run this script again.\n");
}
$config = include __DIR__ . '/../config.php';

if(count($argv)<3) {
	die("USAGE: callService.php {serviceName} {requestFilePath} \n\n");
}

$serviceName = $argv[1];
$requestFile = $argv[2];

if(!file_exists($requestFile)) {
	die("requestFile  " . $requestFile . " does NOT exist!\n\n");
}

$ctrl = new \stdclass();
$ctrl->config = $config;
$ctrl->postedRaw = file_get_contents($requestFile);
$json_data = json_decode($ctrl->postedRaw, true);
if($json_data == null){
      die("Could not parse json. " . json_last_error());
}
$ctrl->requestDoc = \Utils::arrayToObj($json_data);
if(!property_exists($ctrl->requestDoc,'mdml:endpoint')) {
	die("No mdml:endpoint is defined in the request file!\n\n");
}
$params = array();
$params['mdml:endpoint'] = $ctrl->requestDoc->{'mdml:endpoint'};
$ctrl->postedData = array();
$ctrl->queryStr = NULL;

$classpath = chr(92)."coreServices".chr(92).$serviceName;

if(!class_exists($classpath)) {
	die("Class " . $classpath . " does NOT exist!\n\n");
}

$service = new $classpath($ctrl,NULL,$params);

$service->run();

die("Done.\n\n");


?>
