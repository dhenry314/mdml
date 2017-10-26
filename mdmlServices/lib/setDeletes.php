<?php

namespace mdml;

class setDeletes extends EndpointClient {

	var $endpoints = array();
	var $messages = array();

	/**
	* constructor
	*/
	function __construct($serviceArgs,$request,$response,$allowablePaths) {
		$this->endpoints = $serviceArgs['endpoints'];
		parent::__construct($serviceArgs,$request,$response,$allowablePaths);
	}
    
    	public function run() {
		foreach($this->endpoints as $endpoint) {
			$info = $this->info($endpoint);
			$endpointTotal = $info->total;
			$n=0;
			$count = 20;
			while($n < $endpointTotal) {
				$paging = array('offset'=>$n,'count'=>$count);
				$docs = $this->resourceList($endpoint,$paging);
				foreach($docs as $doc) {
					$url = $doc->{'mdml:sourceURI'};
					if(!Utils::protectedURLExists($url,$this->jwt)) {
						$this->messages[] = $url. " does not exist!";
						$this->delete($doc->loc);
						$this->messages[] = "Deleted " . $doc->loc;
					}
				}
				$n += $count;
			}
		}
		$this->response = $this->messages;
		return parent::run();
    	}

}

