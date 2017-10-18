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
/*
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
*/
try {
	$connection = new \MongoDB\Client("mongodb://mdmlAdmin:tw0htbc@ds131524-a0.mlab.com:31524,ds131524-a1.mlab.com:31524/admin?replicaSet=rs-ds131524",array("connectTimeoutMS"=>300000,"serverSelectionTryOnce"=>false));
} catch(Exception $e) {
	die("Could not connect: " . $e->getMessage());
}
try{
	$mongoDB =  $connection->selectDatabase("mdml-endpoints");
} catch(Exception $e) {
	die("Could not select database." . $e->getMessage());
}

try{
	$coll = $mongoDB->selectCollection('mdml');
} catch(Exception $e) {
	die("Could not select collection. " . $e->getMessage());
}

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

/*
try
{
    $mongoDB = $connection->selectDatabase($config['mongo']['database']);
    $coll = $mongoDB->selectCollection('mdml');
    $cursor = $coll->find();
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

if(!$cursor) die("No results for query: " . print_r($query));

$results = array();
$n=0;
foreach($cursor as $doc) {
	echo $n."\n";
	$n++;
	if($n>1000) break;
}
*/
die("Done. \n\n");

?>
