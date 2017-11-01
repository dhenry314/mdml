<?php

namespace mdml;

use Elasticsearch\ClientBuilder;
use \SimpleXMLElement;

class deleteFromES extends Service {

	/**
	* esColl - elasticSearch collection
	*/
	public $scrollingColl;

	public $deletingColl;

	/**
	* esIndex
	*/
	public $esIndex;

	/**
	* esType
	*/
	public $esType;

	public $serviceClient;

	public $endpoints = array();

	public $messages = array();

	/**
	* constructor
	*/
	function __construct($serviceArgs,$request,$response,$allowablePaths) {
		parent::__construct($serviceArgs,$request,$response,$allowablePaths);
		if(!array_key_exists('ESIndex',$serviceArgs)) {
			throw new ServiceException("No elasticsearch index defined.");
		}
		if(!array_key_exists('ESType',$serviceArgs)) {
			throw new ServiceException("No elasticsearch type defined.");
		}
		$cfg = $this->config;
		$this->serviceClient = new ServiceClient($this->jwt);
		$this->scrollingColl = ClientBuilder::create()->setHosts(
						$cfg['elasticSearch']['hosts']
						)->build();
		$this->deletingColl = ClientBuilder::create()->setHosts(
                                               $cfg['elasticSearch']['hosts']
                                                )->build();
		$this->esIndex = $serviceArgs['ESIndex'];
		$this->esType = $serviceArgs['ESType'];
	}

	public function run() {
 		$query = '{
         		"query": {
         			"match_all": {}
    			}
		}';
		$params = array(
    			'search_type' => 'scan',    // use search_type=scan
    			'scroll' => '30s',
    			'size' => 100,
    			'index' => $this->esIndex,
    			'type' => $this->esType,
    			'body' => $query
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
            		"scroll" => "30s"           // and the same timeout window
        		]
    		     );
 
    		     // Check to see if we got any search hits from the scroll
    		     if (count($response['hits']['hits']) > 0) {
         		foreach($response['hits']['hits'] as $doc) {
				$id = $doc['_id'];
				if(!$this->endpointExists($id)) {
					throw new ServiceException("No endpoint found for " . $id);
				}
				if(!Utils::protectedURLExists($id,$this->jwt)) {
					$this->deleteDoc($id);
					$this->messages[] = "Removed " . $id;
				}
         		}
         		$scroll_id = $response['_scroll_id'];
     		     } else {
         		// No results, scroll cursor is empty.  You've exported all the data
         		break;
    		     }
		}

		$this->response = $this->messages;
		return true;
	}

	private function endpointExists($id) {
		$endpoint = $this->endpointFromID($id);
		if(!in_array($endpoint,$this->endpoints)) {
			if(!Utils::protectedURLExists($endpoint,$this->jwt)) {
				return FALSE;
			} else {
				$this->endpoints[] = $endpoint;
				return TRUE;
			}
		} else {
			return TRUE;
		}
	}

	private function endpointFromID($id) {
		$parts = explode("/",$id);
		array_pop($parts);
		$remainder = implode("/",$parts);
		return $remainder;
	}

	protected function deleteDoc($id) {
		$params = array(
			'index' => $this->esIndex,
			'type' => $this->esType,
			'id' => $id
		);
		try {
			$this->deletingColl->delete($params);
		} catch(\Exception $e) {
			throw new \Exception("Could not delete document. ERROR: " . $e->getMessage());
		}
		return true;
	}
	
}

