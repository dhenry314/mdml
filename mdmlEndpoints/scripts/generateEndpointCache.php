#!/usr/bin/php
<?php

//usage
if(count($argv)<2) {
        die("USAGE: generateEndpointCache.php {endpoint} e.g. MOHUB/FRBSTL/FRASER/INCOMING/\n\n");
}

$endpoint = $argv[1];

require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';
$cacheDir = $config['cacheDir'];
if(substr($cacheDir,-1,1)!="/") $cacheDir .= "/";

$dirs = explode("/",$endpoint);
$lastKey = count($dirs)-1;
if(strlen($dirs[$lastKey])==0) array_pop($dirs);
$filename = array_pop($dirs).".json";
$currentDir = $cacheDir.array_shift($dirs)."/";
foreach($dirs as $dir) {
	if(!is_dir($currentDir)) {
		mkdir($currentDir);
	}
	$currentDir .= $dir."/";
}
if(!is_dir($currentDir)) {
      mkdir($currentDir);
}

$fh = fopen($currentDir.$filename,"w+");
//start an array
fwrite($fh,"[\n");

use Elasticsearch\ClientBuilder;
$esURL = $config['elasticsearch']['host'].":".$config['elasticsearch']['port'];
#$indexName = $config['elasticsearch']['indexName'];
$indexName = "resources";
$hosts = array($esURL);   

$client = ClientBuilder::create()->setHosts($hosts)->build();
$query = '{
              "query" : {
                   "bool" : {
                      "must": [
			  {
			    "match_phrase": { "mdml_endpoint": "'.$endpoint.'" }
			  }
                      ]
                   }
              }
   }';

	$params = array(
                "search_type" => "scan",
                "scroll" => "30s",
                "size" => 50,
                "index" => $indexName,
                "type" => "resource",
                "body" => $query
        );

        $docs = $client->search($params);
        $scroll_id = $docs['_scroll_id'];
	$types = array();
	$n=0;
        while(\true) {
                $response = $client->scroll(
                        array(
                                "scroll_id" => $scroll_id,
                                "scroll" => "30s"
                        )
                );
                if (count($response['hits']['hits']) > 0) {
                        $results = array();
                        foreach($response['hits']['hits'] as $result) {
				echo $n."\n";
				$json = json_encode($result['_source']);
				if($n>0) $json = ",\n".$json;
				fwrite($fh,$json);
				$n++;
                        }
                        $scroll_id = $response['_scroll_id'];
                } else {
                        break;
                }
        }
	//close the array
	fwrite($fh,']');
	fclose($fh);

	echo "Wrote " . $n . " resource records to " . $currentDir.$filename . "\n\n";

echo "Done.\n\n";

?>

