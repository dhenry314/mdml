<?php

namespace mdml;
use Exception;

class OAIParser {

   public $base_url;
   public $source_contents;
   protected $filter_params = array('set','from','until','metadataPrefix');
   public $filters = array();
   public $resumption_token;
   public $completeListSize;
   public $currentCount=0;
   public $debug = FALSE;

   function __construct($source_url,$debug=FALSE) {
	libxml_use_internal_errors(true);
	if(!$this->isOAI($source_url)) {
		throw new Exception("URL provided does not return a valid OAI-PMH response.");
		return FALSE;
	}
    $source_contents = $this->getCleanContents($source_url);
	$this->source_contents = $source_contents;
	$source_xml = $this->getXML($source_contents);
	$request = $source_xml->request[0];
	$this->base_url = (string)$request;
	$this->setFilters($source_url);
	return true;
   }

  private function getItemCount($results,$verb) {
	$tagsByVerb = array('ListRecords'=>'record','ListIdentifiers'=>'header','ListMetadataFormats'=>'metadataFormat','ListSets'=>'set');
	if(property_exists($results,$verb)) {
		$verb_result = $results->{$verb};
		if(property_exists($verb_result,$tagsByVerb[$verb])) {
			return count($verb_result->{$tagsByVerb[$verb]});
		}
	} 
	return 0;
  }

 

   private function setFilters($url) {
	$query = str_replace($this->base_url,'',$url);
	if(substr($query,0,1)=='?') {
		$query = substr($query,1);
		foreach (explode('&', $query) as $chunk) {
    			$param = explode("=", $chunk);
			if (in_array($param[0],$this->filter_params)) {
				$this->filters[$param[0]] = $param[1]; 
    			}
		}		
	}
	return false;
   }

   private function getXML($contents) {
	$sxe = simplexml_load_string($contents);
	if ($sxe === false) {
		//add spacing and try again
		$xmlns_split = explode("xmlns",$contents);
		$new_contents = implode(" xmlns",$xmlns_split);
		$sxe = simplexml_load_string($new_contents);
		if($sxe === false) {
    			echo "Failed loading XML\n";
    			foreach(libxml_get_errors() as $error) {
        			echo "\t", $error->message;
    			}
			echo "contents: " . $new_contents;
		}
	}
	return $sxe;
   }

   protected function getCleanContents($url) {
	$contents = @file_get_contents($url);
	if($contents === FALSE) {
		return FALSE;
	}
	$fileContents= trim($contents);
	if(!strstr($fileContents,"<?xml ")) {
		die("Non-xml contents returned: begin:" . $fileContents . ":end \n");
	}
	//eliminate anything before the xml declaration
	$x_split = explode("<?xml ",$fileContents);
	$fileContents = "<?xml ".$x_split[1];
	$fileContents = utf8_encode($fileContents);
        $fileContents = str_replace(array("\n", "\r", "\t"), '', $fileContents);
        $fileContents = trim(str_replace('"', "'", $fileContents));
	return $fileContents;
   }

   protected function remoteObj($url) {
	$obj=FALSE;
        if($fileContents= $this->getCleanContents($url)) {
        	$simpleXml = $this->getXML($fileContents);
        	$json = json_encode($simpleXml);
        	$obj = json_decode($json);
	}
        return $obj;
   }
   
   protected function isOAI($url) {
        if($fileContents= $this->getCleanContents($url)) {
        	$simpleXml = $this->getXML($fileContents);
        	$rootTag = $simpleXml->getName();
        	$rootTag = strtolower($rootTag);
        	if($rootTag == 'oai-pmh') {
                	return true;
        	}
	}
        return false;
    }

    public function Identify() {
	$id_url = $this->base_url."?verb=Identify";
	$id_obj = $this->remoteObj($id_url);
	return $id_obj->Identify;
    }

    public function ListMetadataFormats() {
	return $this->Call('ListMetadataFormats');
    }

    public function Call($verb,$params=array()) {
	 if($this->completeListSize > 0) {
		if($this->currentCount >= $this->completeListSize) {
			//echo "CompleteListSize: " . $this->completeListSize . " currentCount: " . $this->currentCount . "\n";
                	//return FALSE;
		}
        }
	$url = $this->base_url."?verb=".$verb;
	$params_str = NULL;
	if($this->resumption_token) {
		$token=NULL;
		if(is_object($this->resumption_token)) {
			$token = $this->resumption_token->__toString();
		} elseif(is_string($this->resumption_token)) {
			$token = $this->resumption_token;
		}
                $url .= "&resumptionToken=".$token;
        } else {
	   foreach($params as $key=>$val) {
		if(strlen($val)>0) {
                	$params_str .= "&".$key."=".$val;
		}
           }
	   if($params_str) {
		$url .= $params_str;
	   } 
	}
	if($this->debug) {
		echo "Calling oai with url: " . $url . "\n";
	}
	$fileContents= $this->getCleanContents($url);
        $results = $this->getXML($fileContents);
	if(property_exists($results,$verb)) {
		$this->currentCount += $this->getItemCount($results,$verb);
		if(strlen($results->{$verb}->resumptionToken)>0) {
			$this->resumption_token = $results->{$verb}->resumptionToken;
			foreach($this->resumption_token->attributes() as $name=>$val) {
				if($name == 'completeListSize') {
					$this->completeListSize = $val;
				}
			}
			if($this->completeListSize > 0) { 
				if($this->currentCount >= $this->completeListSize) {
					//return FALSE;
					echo "currentCount: " . $this->currentCount . " completeListSize: " . $this->completeListSize . "\n";
				}
				//echo "currentCount: " . $this->currentCount . "\n";
			}
		}
		return $results->{$verb};
	} elseif(property_exists($results,'error')) {
		echo "ERROR: " . print_r($results) . "\n\n";
	} 	
	return FALSE;
    }

    public function GetRecord($identifier,$metadataPrefix) {
        $url = $this->base_url."?verb=GetRecord&identifier=".$identifier."&metadataPrefix=".$metadataPrefix;
	if(!$fileContents= $this->getCleanContents($url)) {
		return FALSE;
	}
        $results = $this->getXML($fileContents);
	return $results->GetRecord;
    }

    public function ListIdentifiers($set=NULL,$metadataPrefix=NULL) {
	$params = array();
	if($set) {
		$params['set'] = $set;
	}
	if($metadataPrefix) {
		$params['metadataPrefix'] = $metadataPrefix;
	}
	return $this->Call('ListIdentifiers',$params);
    }

    public function ListRecords($set=NULL,$metadataPrefix=NULL) {
        return $this->Call('ListRecords',array('set'=>$set,'metadataPrefix'=>$metadataPrefix));
    }

    public function ListSets() {
	return $this->Call('ListSets',array());
    }

    public function clearToken() {
	$this->resumption_token = NULL;
	$this->completeListSize = NULL;
   	$this->currentCount = 0;
    }
}

?>

