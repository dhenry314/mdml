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
        die("USAGE: mongoQuery.php {query as field{:}value - no spaces} e.g. mdml:resource.mdml_endpoint{:}MOHUB/FRBSTL/FRASER/DPLA/ \n\n
	NOTE: Sometimes the query needs to escaped and quoted - for example: ./mongoQuery.php \\@id{:}\"http://data.mohistory.org/mdml/ENDPOINTS/MOHUB/FRBSTL/FRASER/Dttps_fraser.stlouisfed.org/oai?verb=GetRecord&identifier=oai%3Afraser.stlouisfed.org%3Atitle%3A1069\"\n\n");
}

$queryStr = $argv[1];

$q_parts = explode('{:}',$argv[1]);

$query = array($q_parts[0]=>$q_parts[1]);

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
    $cursor = $coll->find($query);
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
foreach($cursor as $doc) {
	$result = json_decode(json_encode( $doc->getArrayCopy() ));
	die("result: " . print_r($result));
}

die("Done. \n\n");

?>
