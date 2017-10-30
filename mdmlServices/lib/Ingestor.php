<?php

namespace mdml;

class InvalidIngest extends \InvalidArgumentException{};

class Ingestor extends Service {

  var $targetEndpoint;
  var $docCount = 0;
  var $messages = array();

  function __construct($serviceArgs,$request,$response,$allowablePaths) {
	parent::__construct($serviceArgs,$request,$response,$allowablePaths);
	// mdml:targetEndpoint MUST exist in serviceArgs
	if(!array_key_exists('mdml:targetEndpoint',$serviceArgs)) {
		throw new InvalidIngest("No mdml:targetEndpoint given!");
	}
    $this->targetEndpoint = $serviceArgs['mdml:targetEndpoint'];
    //remove filename from targetEndpoint if it exists
    $dParts = explode(".",$this->targetEndpoint);
    $ext = array_pop($dParts);
    if(in_array($ext,array("xml","json"))) {
			$sParts = explode("/",$this->targetEndpoint);
			array_pop($sParts);
			$this->targetEndpoint = implode("/",$sParts);
	}
  }

  public function run() {
	$this->response = array("targetEndpoint"=>$this->targetEndpoint,"docCount"=>$this->docCount,
				"messages"=>$this->messages,"errors"=>$this->errors);
	return parent::run();
  }

  public function recordBySourceURI($sourceURI) {
        $authorization = "Authorization: Bearer ".$this->jwt;
        $ch = curl_init();
		$url = $this->targetEndpoint.'?filter=sourceURI:"'.$sourceURI.'"';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        if(curl_errno($ch)){
                throw new \Exception("Curl error: "  . curl_error($ch));
        }
        curl_close($ch);
		return json_decode($result);
  }

  public function deleteRecord($sourceURI) {
	$record = NULL;
	$records = $this->recordBySourceURI($sourceURI);
	if(is_array($records)) {
		if(count($records)==0) {
			throw new \Exception("Cannot delete record. No record in endpoint for sourceURI: " . $sourceURI); 
		}
		if(count($records)>1) {
			throw new \Exception("Cannot delete record. No single matching record in endpoint for sourceURI: " . $sourceURI);
		}
		if(count($records)==1) {
			$record = $records[0];
		}
	} elseif(is_object($records)) {
		if(property_exists($records,'exception')) {
			$errorMsg = $records->exception;
			if(property_exists($records,'message')) {
				$errorMsg .= ": " . $records->message;
			}
			throw new \Exception("Could not delete record with sourceURI: " . $sourceURI .
					" ERROR: " . $errorMsg);
		}
		throw new \Exception("An unknown error occurred attempting to delete sourceURI: " . 
					$sourceURL . 
					" ERROR: " . print_r($records)
				);
	} 
	$authorization = "Authorization: Bearer ".$this->jwt;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->targetEndpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,$json);
    $result = curl_exec($ch);
    if(curl_errno($ch)){
            throw new \Exception("Curl error: "  . curl_error($ch));
    }
    curl_close($ch);
  }

  public function writeToTarget($original_record,$sourceURI) {
  	$errors = array();
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
		$errData = $this->getErrorData($sourceURI,$sourceURI);
		$errors[] = $errData;
     		throw new RecordException("Curl error: "  . curl_error($ch),$errData,"ERROR");
 	}
 	curl_close($ch);
 	$returnObj = json_decode($result);
	if(property_exists($returnObj,'exception')) {
		$errData = $this->getErrorData($sourceURI,$sourceURI,'ERROR');
		$errors[] = $errData;
		throw new RecordException($returnObj->exception.": " . $returnObj->message,$errData,"ERROR");
	} elseif(property_exists($returnObj,'mdml:payload') && property_exists($returnObj,'mdml:sourceURI')) {
		$this->docCount++;
		$errData = $this->getErrorData($sourceURI,$sourceURI);
		if(in_array("INFO",$this->logLevels)) {
			$errors[] = $errData;
			throw new RecordException("Record ingested.",$errData);
		}
	} else {
		$errData = $this->getErrorData($sourceURI,$sourceURI);
		$errors[] = $errData;
		throw new RecordException("Unknown error: Could not ingest.",$errData,"ERROR");
	}
	if(count($errors)>0) {
		$this->messages[] = "ERROR ingesting " . $sourceURI . print_r($errors);
	} else {
		$this->messages[] = "Ingested " . $sourceURI;
	}
	return TRUE;
  }
  
}

?>
