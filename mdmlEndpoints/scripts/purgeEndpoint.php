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
use Elasticsearch\ClientBuilder;
$esURL = $config['elasticsearch']['host'].":".$config['elasticsearch']['port'];
$indexName = $config['elasticsearch']['indexName'];
$hosts = array($esURL);

$client = ClientBuilder::create()->setHosts($hosts)->build(); /* for scrolling query */
$client2 = ClientBuilder::create()->setHosts($hosts)->build(); /* for deletes */

require_once('common.inc');
//usage
if(count($argv)<2) {
	die("USAGE: purgeEndpoint.php {endpoint} e.g. MOHUB/FRBSTL/FRASER/INCOMING/\n\n");
}

$endpoint = $argv[1];

$token = getToken();

if(!$info = getEndpointInfo($endpoint,$token)) {
	die("Endpoint " . $endpoint . " does not exist!\n");
}

$processIDs = getProcessIDs($endpoint,$token);
if(count($processIDs)>0) {
	echo "Please select processID(s) to delete: \n";
	foreach($processIDs as $k=>$processID) {
		echo $k+1 . ") " . $processID . "\n";
	}
	echo $k+2 . ") Delete all log messages for given endpoint.\n\n";
	$handle = fopen ("php://stdin","r");
	$line = trim(fgets($handle));
	$processKey = FALSE;
	if((int)$line == count($processIDs)+1) {
		echo("all log messages will be deleted.\n\n");
	} else {
		$processKey = (int)($line);
	}
	foreach($processIDs as $k=>$processID) {
		if($processKey) {
			$checkKey = $k+1;
			if($checkKey != $processKey) continue;
		}
		echo "Removing logs by processID: " . $processID . "\n";
		removeLogsByProcessID($processID,$token);
	}
}

if(($info->documents + $info->resources)==0) die("There are no documents or resources in " . $endpoint . "\n\n");

echo "You will be removing " . $info->documents . " documents and ". $info->resources ." resources from the endpoint " . $endpoint . "\n";
echo "Do you want to proceed? (y|N) \n";
$handle = fopen ("php://stdin","r");
$line = trim(fgets($handle));
$continue = FALSE;
if(strtolower($line)=='y' || strtolower($line)=='yes') {
	$continue = TRUE;
} 
if(!$continue) die("Ok. Cancelling.\n\n");
echo "Proceeding ... \n\n";

//elasticsearch
$query = '{
	"query": {
    	"match_phrase": {"mdml_endpoint":"'.$endpoint.'"}
    }
}';
$params = array(
    'search_type' => 'scan',    // use search_type=scan
    'scroll' => '30s',
    'size' => 100,    
    'index' => 'resources',
    'type' => 'resource',
    'body' => $query 
);
try {
    $response = $client->search($params);
} catch (\Exception $e) {
    die($e->getMessage());
}
$scroll_id = $response['_scroll_id'];
while (true) {

    // Execute a Scroll request
    $response = $client->scroll([
            "scroll_id" => $scroll_id,  //...using our previously obtained _scroll_id
            "scroll" => "30s"           // and the same timeout window
        ]
    );

    // Check to see if we got any search hits from the scroll
    if (count($response['hits']['hits']) > 0) {
	 foreach($response['hits']['hits'] as $doc) {
                $params = array(
                        'index' => $doc['_index'],
                        'type' => $doc['_type'],
                        'id' => $doc['_id']
                );
                echo("Deleting " . $doc['_id'] . "\n");
                try {
                        $client2->delete($params);
                } catch (\Exception $e) {
                        die($e->getMessage());
                }
        }
        $scroll_id = $response['_scroll_id'];
    } else {
        // No results, scroll cursor is empty.  You've exported all the data
        break;
    }
}


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
    $coll->deleteMany(array('mdml:resource.mdml_endpoint'=>$endpoint));
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


die("Done. \n\n");

?>
