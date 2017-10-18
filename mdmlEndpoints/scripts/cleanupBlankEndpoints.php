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

$token = getToken();

//elasticsearch
$query = '{
    "query" : {
        "constant_score" : {
            "filter": {
                "missing" : { "field" : "mdml_endpoint" }
            }
        }
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

die("Done. \n\n");

?>
