<?php

namespace mdml;

class PushService extends \mdml\Service {

	var $originURI;
	var $process;
	var $processes = array();
	var $messages = array();
	var $startTime;

    function __construct($serviceArgs,$request,$response,$allowablePaths) {
      parent::__construct($serviceArgs,$request,$response,$allowablePaths);
    }
  
    protected function loadProcess($processName) {
	  $processPath = $this->processPath.$processName.".json";
	  if(!file_exists($processPath)) {
		    throw new \mdml\ServiceException("Could not load given process with name " . $processName);  
	  }
	  $processContents = @file_get_contents($processPath);
	  return \mdml\Utils::jsonToObj($processContents);
	} 

	protected function getSourceURI($endpoint,$originURI) {
		$url = $endpoint."?format=json&field=originURI&value=".$originURI;
		$contents = \mdml\Utils::getFromURL($url,$this->jwt);
		if(count($contents)==0) return FALSE;
		if(count($contents)>1) return FALSE;
		return $contents[0]->loc;
	}

	public function run() {
		$this->startTime = microtime(true);
		//Iterate through processes and run each
		// the first sourceEndpoint MUST have the originURI in it; If not, it MUST be written to it
		$firstProcess = array_shift($this->processes);
		$firstSourceEndpoint = $firstProcess->sourceEndpoint;
		if(!$original_record = \mdml\Utils::getFromURL($this->originURI,$this->jwt)) {
			throw new \mdml\ServiceException("Could not load " . $this->originURI);
		}
		$this->writeToTarget($original_record,$firstSourceEndpoint,$this->originURI);
		if(!$firstSourceURI = $this->getSourceURI($firstSourceEndpoint,$this->originURI)) {
			throw new \mdml\ServiceException("Could not get sourceURI from originURI: " . $this->originURI);
		}
		array_unshift($this->processes,$firstProcess);
		foreach($this->processes as $nextProcess) {
			$processResult = $this->runProcess($nextProcess);
			if(!$processResult) {
				$msg = "Could not process " . $nextProcess->service->methodname;
				$this->messages[] = $msg;
				continue;
			}
			$elapsedTime = microtime(true) - $this->startTime;
			if(property_exists($processResult,'fault')) {
				$msg = "Could not process " . $nextProcess->service->methodname;
				$msg .= " faultcode:  " . $processResult->fault->code;
				$msg .= " faultstr: " . $processResult->fault->string;
			} else {
				$msg = "Processed " . $nextProcess->service->methodname . " in " . $elapsedTime;
			}
			$this->messages[] = $msg;
		}
		$this->response = $this->messages;
		parent::run();
    }
    
    protected function runProcess($process) {
    		if(!property_exists($process,"sourceEndpoint")) return FALSE;
		$sourceURI = $this->getSourceURI($process->sourceEndpoint,$this->originURI);
		if(!property_exists($process->service->args,"mdml:sourceURI")) return FALSE;
		$process->service->args->{'mdml:sourceURI'} = $sourceURI;
		$process->service->args->{'mdml:originURI'} = $this->originURI;
		$returnObj = FALSE;
		if($result = \mdml\Utils::postToURL($process->serviceURI,$process->service,$this->jwt)) {
			if(property_exists($process,'targetEndpoint')) {
				$returnObj = $this->writeToTarget($result->result,$process->targetEndpoint,$sourceURI,$this->originURI);
			} else {
				$returnObj = $result;
			}
		}
		return $returnObj;
	}
    
    protected function writeToTarget($original_record,$targetEndpoint,$sourceURI,$originURI=NULL) {
		if(!$originURI) $originURI = $sourceURI;
		$doc = new \mdml\mdmlDoc($original_record,$sourceURI,$originURI);
		$json = Utils::safe_json_encode($doc);
		//remove filename from target endpoint
		$eParts = explode(".",$targetEndpoint);
		$ext = array_pop($eParts);
		if(in_array($ext, array('json','xml'))) {
			$sParts = explode("/",$targetEndpoint);
			array_pop($sParts);
			$targetEndpoint = implode("/",$sParts);
			$targetEndpoint = $targetEndpoint . "/";
		}
		$authorization = "Authorization: Bearer ".$this->jwt;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $targetEndpoint);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$json);
		$result = curl_exec($ch);
		if(curl_errno($ch)){
			throw new ServiceException("Could not write to endpoint with sourceURI: " . $sourceURI . " Curl error: "  . curl_error($ch));
		}
		curl_close($ch);
		$returnObj = json_decode($result);
		if(property_exists($returnObj,'exception')) {
			throw new ServiceException($returnObj->exception.": " . $returnObj->message,$errData,"ERROR");
		} elseif(!property_exists($returnObj,'mdml:payload')) {
			throw new ServiceException("Unknown error: Could not ingest. SourceURI: " . $sourceURI);
		}
		return $returnObj;
  }
  
}

?>
