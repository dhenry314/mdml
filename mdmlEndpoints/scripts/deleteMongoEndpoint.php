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

//usage
if(count($argv)<2) {
        die("USAGE: deleteMongoEndpoint.php {endpointPath}"); 
}

$path = $argv[1];

//escape forward slashes
$path = str_replace("/","\\/",$path);

$query = array('@id' => new \MongoDB\BSON\Regex("/$path/"));

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

try
{
    $mongoDB = $connection->selectDatabase($config['mongo']['database']);
    $coll = $mongoDB->selectCollection('mdml');
    $count = $coll->count($query);
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

echo ("Found " . $count . " docs.  Do you want to delete these?  y|N ");
$handle = fopen ("php://stdin","r");
$line = fgets($handle);
if(strtolower(trim($line)) != 'y') exit;

try {
	$coll->deleteMany($query);
} catch(\Exception $e) {
	die("Could not delete docs. ERROR: " . $e->getMessage());
}

die("Done. \n\n");

?>
