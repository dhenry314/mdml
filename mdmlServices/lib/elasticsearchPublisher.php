<?php

namespace mdml;

use Elasticsearch\ClientBuilder;
use \SimpleXMLElement;

class InvalidElasticSearchPublisher extends \InvalidArgumentException{};
 
class elasticsearchPublisher extends Service {

	/**
	* esColl - elasticSearch collection
	*/
	public $esColl;

	/**
	* esIndex
	*/
	public $esIndex;

	/**
	* esType
	*/
	public $esType;

	public $sourceURI;

	public $serviceClient;

	public $sourceDoc;

	/**
	* constructor
	*/
	function __construct($serviceArgs,$request,$response,$allowablePaths) {
		parent::__construct($serviceArgs,$request,$response,$allowablePaths);
		if(!array_key_exists('mdml:sourceURI',$serviceArgs)) {
			throw new InvalidElasticSearchPublisher("No mdml:sourceURI defined.");
		}
		$this->sourceURI = $serviceArgs['mdml:sourceURI'];
		$cfg = $this->config;
		$this->serviceClient = new ServiceClient($this->jwt);
		if(!$docResult = $this->serviceClient->get($this->sourceURI)) {
                         throw new InvalidElasticSearchPublisher(
					"Could not load input document from given sourceURI: "
                                         . $this->serviceArgs['mdml:sourceURI']
					);
                }
                if(is_object($docResult)) {
                        $this->sourceDoc = $docResult->{'mdml:payload'};
                } elseif(is_array($docResult)) {
                        $this->sourceDoc = $docResult['mdml:payload'];
                }
		$this->esColl = ClientBuilder::create()->setHosts($cfg['elasticSearch']['hosts'])->build();
		$this->esIndex = $cfg['elasticSearch']['index'];
		$this->esType = $cfg['elasticSearch']['type'];
		//use given parameters as necessary
		if(array_key_exists('esIndex',$serviceArgs)) {
			$this->esIndex = $serviceArgs['esIndex'];
		}
		if(array_key_exists('esType',$serviceArgs)) {
			$this->esType = $serviceArgs['esType'];
		}
	}

	public function run() {
		$this->index();
		$this->response = array(
			"content"=>"Successfully indexed " . $this->sourceURI . " to " . $this->esIndex
		);
		return true;
	}

	protected function index() {
		$params = array(
			'index' => $this->esIndex,
			'type' => $this->esType,
			'id' => $this->sourceURI,
			'body' => $this->sourceDoc
		);
		try {
			$this->esColl->index($params);
		} catch(\Exception $e) {
			throw new \Exception("Could not index document. ERROR: " . $e->getMessage());
		}
		return true;
	}

	protected function convertToESFields($namespaces=array()) {
		$docJ = Utils::safe_json_encode($this->sourceDoc);
		foreach($namespaces as $ns) {
			$docJ = str_replace($ns.":",$ns."_",$docJ);
		}
		$this->sourceDoc = Utils::jsonToObj($docJ);
		return true;
	}
	
}

