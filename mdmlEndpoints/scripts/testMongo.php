#!/usr/bin/php
<?php

set_time_limit(0);

require __DIR__ . '/../vendor/autoload.php';

$config = include __DIR__ . '/../config.php';

//build a connection string
$servers = "ds131524-a0.mlab.com:31524,ds131524-a1.mlab.com:31524";
$replicaSet = "rs-ds131524";
$database = "mdml-endpoints";
$options = array("connectTimeoutMS"=>300000,"serverSelectionTryOnce"=>false);
$connection_str = sprintf("mongodb://%s:%s@%s/%s?replicaSet=%s",
			$config['mongo']['adminUser'],
			$config['mongo']['adminPW'],
			$servers,
			$database,
			$replicaSet);

//create connection
try {
	$connection = new \MongoDB\Client($connection_str,$options);
} catch(Exception $e) {
	die("Could not connect: " . $e->getMessage());
}

//select database
try{
	$mongoDB =  $connection->selectDatabase("mdml-endpoints");
} catch(Exception $e) {
	die("Could not select database." . $e->getMessage());
}

//select collection
try{
	$coll = $mongoDB->selectCollection('mdml');
} catch(Exception $e) {
	die("Could not select collection. " . $e->getMessage());
}

//insert document
try {
	$insertOneResult = $coll->insertOne([
    		'username' => 'admin',
    		'email' => 'admin@example.com',
    		'name' => 'Admin User',
	]);
} catch(Exception $e) {
	die("Could not insert document. " . $e->getMessage());
}

printf("Inserted %d document(s)\n", $insertOneResult->getInsertedCount());

var_dump($insertOneResult->getInsertedId());

die("Done. \n\n");

?>
