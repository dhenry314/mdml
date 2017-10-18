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

//Mongodb
$connecting_string =  sprintf('mongodb://%s:%d/',
                              $config['mongo']['host'],
                              $config['mongo']['port']
                        );
if(array_key_exists('connectOptions',$config['mongo'])) {
	$options = $config['mongo']['connectOptions'];
} else {
	$options = array();
}
if(array_key_exists('adminPW', $config['mongo']) && array_key_exists('adminUser', $config['mongo'])) {
      $options['username'] = $config['mongo']['adminUser'];
      $options['password'] = $config['mongo']['adminPW'];
} else {
        die("No admin credentials for mongo in the configuration.\n\n");
}
$connection=  new \MongoDB\Client($connecting_string,$options);
$indexes = array();
try
{
    $mongoDB = $connection->selectDatabase($config['mongo']['database']);
    $coll = $mongoDB->selectCollection('mdml');
	foreach ($coll->listIndexes() as $index) {
          $indexes[] = $index;
	}
}
catch (\MongoDB\Driver\Exception\AuthenticationException $e) {
        die("Could not login to MongoDB. Please check Mongo adminUser and adminPW in config.php.\n
           ERROR: " . $e->getMessage() . "\n\n");
}
catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e)
{
        die("Could not list databases. Please check your Mongo configuration.\n
        ERROR: " . $e->getMessage(). "\n\n");
}
catch (\MongoDB\Driver\Exception\RuntimeException $e) {
        die("Could not list databases. Please check your Mongo configuration.\n
        ERROR: " . $e->getMessage() . "\n\n");
}

$updateIndexes = array();
foreach($indexes as $index) {
	$arr = (array)$index;
	$indexInfo = array_shift($arr);
	$key = $indexInfo["key"];
	$updateIndexes[] = array("key"=>$key);
}

$result = $coll->dropIndexes();
echo "dropIndexes result: " . print_r($result) . "\n";
$indexNames = $coll->createIndexes($updateIndexes);
echo "createIndexes result: " . print_r($indexNames) . "\n";

die("Done. \n\n");

?>
