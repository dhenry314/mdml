<?php
namespace mdml;

class InvalidJSONMapping extends \InvalidArgumentException{};

class jsonMapping extends Service {

  public $mapJSON;
  public $map;
  protected $mappingServices = array();
  protected $methodPrefixes = array("_path"=>"path","_var"=>"getMapVar");
  public $serviceClient;
  protected $doc;
  protected $sourceURI;
  protected $originURI;
  protected $namespacesToIgnore = array('ore','http','https','map','mapVars','oai_dc');
  protected $messages = array();
  protected $mapResult;
  protected $mapVars = array();

  public function __construct($serviceArgs,$request,$response,$allowablePaths) {
	      parent::__construct($serviceArgs,$request,$response,$allowablePaths);
	      $this->loadRequest($this->serviceArgs);
        $this->serviceClient = new ServiceClient($this->jwt);
  }

  public function run() {
	$this->setMappingServices();
	if(array_key_exists('mdml:inputDocument',$this->serviceArgs)) {
		$this->doc = $this->serviceArgs['mdml:inputDocument'];
	} elseif(array_key_exists('mdml:sourceURI',$this->serviceArgs)) {
		if(!$docResult = $this->serviceClient->get($this->serviceArgs['mdml:sourceURI'])) {
			throw new InvalidJSONMapping("Could not load input document from given sourceURI: "
							. $this->serviceArgs['mdml:sourceURI']);
		}
		if(is_object($docResult)) {
      if(property_exists($docResult,'ErrorMessage')) {
        throw new InvalidJSONMapping("Could not load input document from given sourceURI: "
                . $this->serviceArgs['mdml:sourceURI'] . "  " .$docResult->ErrorMessage);
      }
			$this->doc = $docResult->{'mdml:payload'};
			$this->sourceURI = $this->serviceArgs['mdml:sourceURI'];
			$this->originURI = $docResult->{'mdml:originURI'};
		} elseif(is_array($docResult)) {
			$this->doc = $docResult['mdml:payload'];
			$this->sourceURI = $this->serviceArgs['mdml:sourceURI'];
			$this->originURI = $docResult['mdml:originURI'];
		}
	}
	if(!$this->doc) {
		throw new InvalidJSONMapping("Could not get input document contents from given arguments.");
	}
	try {
	        $result = $this->mapRecord($this->doc);
	} catch(RecordException $re) {
	        if($re->status != 1) {
	                throw new ServiceException($re->getMessage());
	        }
	} catch(\Exception $e) {
	        throw new ServiceException("Could not process record: " . $e->getMessage());
	}
	$this->response = $this->mapResult;
  return parent::run();
	#return $this->mapResult;
  }

  protected function loadRequest($args) {
	//check for existence of the mapPath
	$mapContents = NULL;
	if(array_key_exists('mdml:mapPath',$args)) {
		if(!$mapContents = Utils::getFileContents($args['mdml:mapPath'])) {
			throw new InvalidJSONMapping("Could not load map from " . $args['mdml:mapPath']);
		}
		$this->mapJSON = $mapContents;
	        $this->reloadMap();
	} elseif(array_key_exists('mdml:map',$args)) {
		$this->map = $args['mdml:map'];
		$this->mapJSON = Utils::safe_json_encode($this->map);
	}
	return TRUE;
  }

  protected function reloadMap() {
        $this->map = $this->toObj($this->mapJSON);
  }

  public function setMappingServices() {
	if(property_exists($this->map,'mdml:mapServices')) {
		$this->mappingServices = (array) $this->map->{'mdml:mapServices'};
	}
  }

  private function toObj($json) {
  	if(is_array($json)) {
		return (object)$json;
	}
        $json = trim($json);
        if(!$obj = json_decode($json)) {
              throw new \Exception('ERROR: cannot parse json. ' . json_last_error_msg() . " json = " . $json);
        }
        return $obj;
  }

  protected function cleanVal($val) {
  	if (is_bool($val) === true) {
		if(!$val) {
			return false;
		} else {
			return true;
		}
	}
        if(is_array($val)) {
		$newArray = array();
                foreach($val as $k=>$part) {
			$newArray[$k] = $this->cleanVal($part);
                }
                return $newArray;
        } elseif(is_object($val)) {
		$newObj = new \stdclass();
                foreach($val as $property=>$v) {
			$newObj->{$property} = $this->cleanVal($v);
		}
                return $newObj;
        }
        $val = stripcslashes($val);
        $val = html_entity_decode($val);
        $val = urldecode($val);
	if(strlen($val)==0) return NULL;
        return $val;
  }

  protected function getMappingMethod($property) {
	$result = array();
	if(!strstr($property,":")) return FALSE;
        $property = trim($property);
	$parts = explode(":",$property);
	if(array_key_exists($parts[0],$this->mappingServices)) {
		$result['object'] = $this->mappingServices[$parts[0]];
		$result['method'] = $parts[1];
		$result['type'] = 'web';
		return $result;
	} elseif(strtolower($parts[0]) == 'map') {
		$result['object'] = $this;
		$result['method'] = $parts[1];
		$result['type'] = 'internal';
		return $result;
	}
	return FALSE;
  }


  protected function mapParams($val) {
        if(is_string($val)) {
                return $this->getVal($val);
        } elseif(is_array($val)) {
                foreach($val as $key=>$subval) {
                        $val[$key] = $this->mapParams($subval);
                }
                return $this->cleanVal($val);
       } elseif(is_object($val)) {
                $obj = $this->mapObject($val);
                $val = $this->cleanVal($obj);
		return $val;
       }
  }

  protected function callMappingService($method,$args) {
	$args = $this->mapObject($args);
	try {
		$result = $this->serviceClient->callService($method['object'],$method['method'],$args);
	} catch(\Exception $e) {
		throw new InvalidJSONMapping("Could not call mapping service. ERROR: " . $e->getMessage());
	}
	return $result;
  }

  protected function mapObject($obj) {
        foreach($obj as $property=>$val) {
                if($method = $this->getMappingMethod($property)) {
			               if($method['type'] == 'internal') {
			                    if(!method_exists($this,$method['method'])) {
				                        throw new InvalidJSONMapping("Internal method does not exist: " . $method['method']);
			                    }
			                    if(!is_callable(array($method['object'],$method['method']))) {
				                        throw new InvalidJSONMapping("Could not create callable method with given property: " . $property);
			                    }
			                    $val = $this->mapParams($val);
                          //send the val as params to the method
                          try {
                                if($result = $method['object']->$method['method']($val,$this->sourceURI)) {
                                        return $result;
                                } else {
                                        return FALSE;
                                }
                          } catch (\Exception $e) {
				                        $this->messages[] = $e->getMessage();
                          }
			               } elseif($method['type'] == 'web') {
				                   return $this->callMappingService($method,$val);
			               } else {
				                   die("Unknown method type: " . $method['type']);
			               }
                } elseif(is_string($val)) {
			               $obj->$property = $this->getVal($val);
		            } elseif(is_array($val)) {
                      $obj->$property = $this->cleanVal($val);
                } elseif(is_object($val)) {
                        $obj->$property = $this->mapObject($val,$this->sourceID);
                }
        }
	      return $obj;
  }

   public function parseMapVars($doc) {
	     if(property_exists($this->map,'mdml:mapVars')) {
		       try {
                $result = $this->mapObject($this->map->{'mdml:mapVars'});
           } catch (\Exception $e) {
                throw new InvalidJSONMapping("Could not parse map vars.  ERROR:  " . $e->getMessage());
           }
		       if(is_object($result)) {
			          $this->mapVars = (array)$result;
		       }
	     }
   }

   public function getMapVar($k) {
	    if(array_key_exists($k,$this->mapVars)) {
		      return $this->mapVars[$k];
	    }
	    return NULL;
   }

   public function extractValue($params) {
       	$data = NULL;
      	$expr = NULL;
       	if(is_array($params)) {
     		   $data = $params['object'];
     		   $expr = $params['path'];
     	  } elseif(is_object($params)) {
     		   $data = $params->object;
     		   $expr = $params->path;
     	  } else {
     		  return NULL;
     	  }
       	$result = \mdml\Utils::JSONSearch($expr,$data);
     	  return $result;
   }


   public function getVal($val) {
	     $val = trim($val);
	     if(!is_string($val)) {
		       throw new \Exception("Parameter MUST be a string in getVal!");
	     }
	     if(substr($val,0,1) == "_") {
		       $valParts = explode(":",$val);
		       $prefix = array_shift($valParts);
		       $remainder = implode(":",$valParts);
		       if(array_key_exists($prefix,$this->methodPrefixes)) {
			          $methodName = $this->methodPrefixes[$prefix];
			          return $this->$methodName($remainder);
		       } else {
			          throw new \Exception("Unknown mapping prefix: " . $prefix);
		       }
	     }
	     return $this->cleanVal($val);
   }


   public function mapRecord($doc) {
        //clear any existing result
        unset($this->mapResult);
        $this->messages = array();
	      $this->mapVars = array();
        $this->mapResult = new \stdClass();
        $this->currentID = $targetID;
        if(!is_object($doc)) {
		        try {
          	   $doc = $this->toObj($doc);
		        } catch (\Exception $e) {
               $this->messages[] = $e->getMessage();
            }
        }
	      if(property_exists($doc,'mdml:payload')) {
		        $doc = $doc->{'mdml:payload'};
		        $this->doc = $doc;
	      }
	      try {
		        $this->parseMapVars($doc);
	      } catch (\Exception $e) {
            $this->messages[] = $e->getMessage();
        }
	      //unset mdml mapping fields
        unset($this->map->{'mdml:mapServices'});
        unset($this->map->{'mdml:mapVars'});
        foreach($this->map as $property=>$val) {
		        if($property == '@context') {
			           $this->mapResult->$property = $val;
		        }
            if(is_string($val)) {
			           try {
				               $this->mapResult->$property = $this->getVal($val);
			           } catch (\Exception $e) {
                       $this->messages[] = $e->getMessage();
                }
		       } elseif(is_array($val)) {
                $this->mapResult->$property = $val;
           } else {
                try {
                    $this->mapResult->$property = $this->mapObject($val);
                } catch (\Exception $e) {
                    $this->messages[] = $e->getMessage();
                }
          }
        }
        if(count($this->messages)>0) {
		        foreach($this->messages as $msg) {
			           $errData = $this->getErrorData($this->sourceURI,$this->originURI);
			           throw new RecordException($msg,$errData,"WARNING");
		        }
		        $this->mapResult->mappingErrors = $this->messages;
        }
        return $this->mapResult;
  }

  /**
  * path
  * @param params - an array of parameters
  *   params[0] = expr in JMSEPath format (see http://jmespath.org/)
  *   params[1] = returnType (optional)
  *   params[2] = defaultValue (optional) - use if nothing returned from search
  * $return - value resulting from JMESPath query
  */
  public function path($params) {
        $returnType = "string";
        $defaultValue = NULL;
        if(is_array($params)) {
                $path = $params[0];
                if(array_key_exists(1,$params)) {
                        $returnType = $params[1];
                }
                if(array_key_exists(2,$params)) {
                        $defaultValue = $params[2];
                }
        } else {
                $path = $params;
        }
        if($result = Utils::JSONSearch($path, $this->doc)) {
                if(strtolower($returnType)=='array') {
                        if(!is_array($result)) {
                                $result = array($result);
                        }
                }
                return $this->cleanVal($result);
        } elseif($defaultValue) {
		return $defaultValue;
	} else {
		return FALSE;
	}
        return $this->cleanVal($defaultValue);
  }

  public function concat($parts) {
	return implode($parts);
  }

  public function getSourceURI() {
	return $this->sourceURI;
  }

  public function getOriginURI() {
	return $this->originURI;
  }

  public function arrayItemFromPattern($params) {
        if(property_exists($params,'array')) {
                if(property_exists($params,'pattern')) {
                        $pattern = $params->pattern;
                        foreach($params->array as $item) {
                                if(strpos($item,$pattern)) {
                                        return $item;
                                }
                        }
                }
        }
        return NULL;
  }

  public function currentDateTime() {
	return date('c');
  }


  public function strReplace($params) {
	return str_replace($params->search,$params->replace,$params->subject);
  }

  public function toLower($params) {
  	$str = NULL;
  	if(!property_exists($params,'str')) return FALSE;
	if(is_array($params->str)) {
		$str = $params->str[0];
	} else {
		$str = $params->str;
	}
	return strtolower($str);
  }

}

?>
