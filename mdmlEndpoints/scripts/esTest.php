#!/usr/bin/php
<?php
require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';
use Elasticsearch\ClientBuilder;
$esURL = $config['elasticsearch']['host'].":".$config['elasticsearch']['port'];
#$indexName = $config['elasticsearch']['indexName'];
$indexName = "mohub_dpla";
#$indexName="ccsearchv2";
$hosts = array($esURL);   

$client = ClientBuilder::create()->setHosts($hosts)->build();
$query2 = '{
              "query" : {
                   "bool" : {
                      "must": [
			  {
			    "match": { "rs_md_hash": "5ce6cf04ed0ffbbe6f2900f0d12cde8b" }
			  }
                      ]
                   }
              }
   }';
$query1 = '{ "query": { "match_phrase": {"mdml:resource.mdml_endpoint":"MOHUB/MHM/ALL/DPLA/"} } }';
$query3 = '{ "query": { "match": {"@id": "http://collections.mohistory.org/resource/156802"}}}';
$query = '{ "query": { "match_all": {} } }';
	$params = array(
                "search_type" => "scan",
                "scroll" => "30s",
                "size" => 50,
                "index" => $indexName,
                "body" => $query1
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
		//DEBUG
		die("total: " . $response['hits']['total']);
                if (count($response['hits']['hits']) > 0) {
                        $results = array();
                        foreach($response['hits']['hits'] as $result) {
				echo $n."\n";
				if($n=600) {
					die("result: " . print_r($result));
				}
				/*
				$recordType = $result['_source']['recordType'];
		                echo $recordType . "\n";
                		if(!array_key_exists($recordType,$types)) {
                        		$types[$recordType] = 1;
                		} else {
                        		$types[$recordType]++;
                		}
				*/
				$n++;
                        }
                        $scroll_id = $response['_scroll_id'];
                } else {
                        break;
                }
        }
	die("types: " . print_r($types));  

echo "Done.\n\n";

?>

