<?php

namespace mdml;

use Elasticsearch\ClientBuilder;
use \SimpleXMLElement;
use Exception;
use stdClass;

class ESingestor extends Ingestor {

	public $scrollingColl;

	/**
	* esIndex
	*/
	public $esIndex;

	/**
	* esType
	*/
	public $esType;

	public $esQuery;

	public $serviceClient;

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
		$this->esIndex = $serviceArgs['ESIndex'];
		$this->esType = $serviceArgs['ESType'];
		if(array_key_exists('ESQuery', $serviceArgs)) {
			 $this->esQuery = $serviceArgs['ESQuery'];
		} else {
			$this->esQuery =  '{
	         				"query": {
	         						"match_all": {}
	    						}
						}';
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
            		"scroll" => "30s"           // and the same timeout window
        ]);

    		// Check to see if we got any search hits from the scroll
    		if (count($response['hits']['hits']) > 0) {
         		foreach($response['hits']['hits'] as $doc) {
							  //DEBUG
								die("doc: " . print_r($doc));
							  $this->writeToTarget($original_record,$sourceURI);
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

}
