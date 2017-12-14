<?php

namespace mdml;

use Elasticsearch\ClientBuilder;
use \SimpleXMLElement;
use Exception;
use stdClass;

class ESingestor {

  public $targetEndpoint;

	public $jwt;

	public $scrollingColl;

	public $esBase;

	/**
	* esIndex
	*/
	public $esIndex;

	/**
	* esType
	*/
	public $esType;

	public $esQuery;

	public $messages = array();

	public $printMessages = TRUE;

	public $docCount;

	public $response;

	/**
	* constructor
	*/
	function __construct($config,$targetEndpoint,$jwt,$printMessages=TRUE) {
		if(!array_key_exists('ESIndex',$config)) {
			throw new ServiceException("No elasticsearch index defined.");
		}
		if(!array_key_exists('ESType',$config)) {
			throw new ServiceException("No elasticsearch type defined.");
		}
		$this->scrollingColl = ClientBuilder::create()->setHosts(
						$config['elasticSearch']['hosts']
						)->build();
		$this->esBase = $config['elasticSearch']['hosts'][0];
		$this->esIndex = $config['ESIndex'];
		$this->esType = $config['ESType'];
		if(array_key_exists('ESQuery', $config)) {
			 $this->esQuery = $config['ESQuery'];
		} else {
			$this->esQuery =  '{
	         				"query": {
	         						"match_all": {}
	    						}
						}';
		}
		$this->targetEndpoint = $targetEndpoint;
		$this->jwt = $jwt;
		if(!$printMessages) {
				$this->printMessages = FALSE;
		}
	}


	public function run() {
		$this->ingest();
		return parent::run();
  }

	public function ingest() {
		$params = array(
    			'search_type' => 'scan',    // use search_type=scan
    			'scroll' => '30s',
    			'size' => 100,
    			'index' => $this->esIndex,
    			'type' => $this->esType,
    			'body' => $this->esQuery
		);
		try {
    			$response = $this->scrollingColl->search($params);
		} catch (\Exception $e) {
    			die($e->getMessage());
		}
		$scroll_id = $response['_scroll_id'];
		while (true) {

	    // Execute a Scroll request
  		$response = $this->scrollingColl->scroll([
          		"scroll_id" => $scroll_id,  //...using our previously obtained _scroll_id
          		"scroll" => "60s"           // and the same timeout window
      ]);
    	// Check to see if we got any search hits from the scroll
    	if (count($response['hits']['hits']) > 0) {
       		foreach($response['hits']['hits'] as $doc) {
						  $sourceURI = $this->esBase."/".$doc['_index']."/".$doc['_type']."/".$doc['_id'];
						  $this->writeToTarget($doc['_source'],$sourceURI);
       		}
       		$scroll_id = $response['_scroll_id'];
    	} else {
     		// No results, scroll cursor is empty.  You've exported all the data
     		break;
      }
		}

		$this->response = $this->messages;
		return TRUE;
	}

	public function writeToTarget($original_record,$sourceURI) {
    	$doc = new mdmlDoc($original_record,$sourceURI);
			$json = Utils::safe_json_encode($doc);
 			$authorization = "Authorization: Bearer ".$this->jwt;
 			$ch = curl_init();
 			curl_setopt($ch, CURLOPT_URL, $this->targetEndpoint);
 			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
 			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 			curl_setopt($ch, CURLOPT_POSTFIELDS,$json);
 			$result = curl_exec($ch);
 			if(curl_errno($ch)){
				$msg = "SourceURI: " . $sourceURI . " Curl error: "  . curl_error($ch);
				if($this->printMessages) {
					echo $msg . "\n";
				} else {
     			$this->messages[] = $msg;
				}
 			}
 			curl_close($ch);
 			$returnObj = json_decode($result);
			if(property_exists($returnObj,'exception')) {
					$msg = "SourceURI: " . $sourceURI . " ERROR: " . $returnObj->exception.": " . $returnObj->message;
					if($this->printMessages) {
						echo $msg . "\n";
					} else {
	     			$this->messages[] = $msg;
					}
			} elseif(property_exists($returnObj,'mdml:payload') && property_exists($returnObj,'mdml:sourceURI')) {
					$this->docCount++;
					$msg = "SourceURI: " . $sourceURI . " ingested. ";
					if($this->printMessages) {
						echo $msg . "\n";
					} else {
	     			$this->messages[] = $msg;
					}
				} else {
					$msg = "SourceURI: " . $sourceURI . " Unknown error: Could not ingest.";
					if($this->printMessages) {
						echo $msg . "\n";
					} else {
	     			$this->messages[] = $msg;
					}
			  }
				return TRUE;
  }

}
