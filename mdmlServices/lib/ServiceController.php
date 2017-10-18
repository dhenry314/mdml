<?php

namespace mdml;

class ServiceRoutingException extends \Exception{};
class InvalidJSONException extends \Exception{};

class ServiceController {

  var $config;
  var $http_method;
  var $request;
  var $response;
  var $allowablePaths;
  var $postedRaw;
  var $postedData;
  var $requestDoc;
  var $methodname;
  var $serviceName;
  var $serviceDefinition;
  var $serviceDefTypes;
  var $serviceArgs = array();
  var $byPassArgs = array('mdml:loggingServiceURI','mdml:loggingTag');
  var $service;

  function __construct($config,$request,$response,$allowablePaths) {
      $this->config = $config;
      $this->http_method = $request->getMethod();
      $this->request = $request;
      $postedRaw = @file_get_contents('php://input');
      if(!$postedRaw) {
	throw new ServiceRoutingException("No request found!");
      }
      $this->postedRaw = $postedRaw;
      try {
	$this->postedData = Utils::jsonToObj($postedRaw);
      } catch(\Exception $e) {
	throw new InvalidJSONException("Could not parse request. ERROR: " . $e->getMessage());
      }
      $this->requestDoc = Utils::arrayToObj($this->postedData);
      $this->loadServiceDefinition();
      $this->queryStr = $request->getUri()->getQuery();
      $this->response = $response;
      $this->allowablePaths = (array) $allowablePaths;
      if(!$this->checkPaths()) {
        throw new ServiceRoutingException("Service requested is not allowed.");
      }
  }

  private function loadServiceDefinition() {
      //the request MUST have a methodname property
      if(!property_exists($this->requestDoc,'methodname')) {
        throw new ServiceRoutingException("No methodname found in request!");
      }
      $methodname = $this->requestDoc->methodname;
      //normalize the methodname
      if(substr($methodname,0,1)=="/") {
        $methodname = substr($methodname,1);
      }
      $this->methodname = $methodname;
      if(!array_key_exists('serviceDescription',$this->config)) {
        throw new ServiceRoutingException("No serviceDescription path found!");
      }
      try {
        $serviceDescription = Utils::ObjFromJSONurl($this->config['serviceDescription']);
      } catch(\Exception $e) {
        throw new ServiceRoutingException("Could not load serviceDescription. ERROR: " . $e->getMessage());
      }
      if(array_key_exists('servicename',$serviceDescription)) {
        $this->serviceName = $serviceDescription['servicename'];
      }
      if(array_key_exists('types',$serviceDescription)) {
	$this->serviceDefTypes = $serviceDescription['types'];
      }
      if(!array_key_exists('methods',$serviceDescription)) {
        throw new ServiceRoutingException("No methods found in service description.");
      }
      $serviceMethods = $serviceDescription['methods'];
      if(!array_key_exists($methodname,$serviceMethods)) {
        throw new ServiceRoutingException("Method " . $methodname . " not found in service description.");
      }
      $this->serviceDefinition = $serviceMethods[$methodname];
      //check args in the request against params defined in the serviceDefinition
      $postedArgs = array();
      if(array_key_exists('args',$this->postedData)) {
      	$postedArgs = $this->postedData['args'];
      }
      foreach($this->serviceDefinition['params'] as $paramName=>$def) {
        $argVal = NULL;
	$argType = 'boolean';
	if(!$def['optional']) {
		if(!isset($this->requestDoc->args->{$paramName})) {
			throw new ServiceRoutingException("Param: " . $paramName . " is required.");
		}
	}
        if(isset($this->requestDoc->args->{$paramName})) {
		$argVal = $this->requestDoc->args->{$paramName};
		$postedVal = NULL;
		if(array_key_exists($paramName,$postedArgs)) {
			$postedVal = $postedArgs[$paramName];
		}
		$argType = gettype($argVal);
		if($argType != $def['type']) {
			if($postedVal) {
				//check postedType
				$postedType = gettype($postedVal);
				if($postedType != $def['type']) {
					throw new ServiceRoutingException("Param " . $paramName . " must be of type " . $def['type']);
				} else {
					$this->serviceArgs[$paramName] = $postedVal;
				}
			} else {
				throw new ServiceRoutingException("Param " . $paramName . " must be of type " . $def['type']);
			}
		} else {
			$this->serviceArgs[$paramName] = $argVal;
		}
	}
      }
      foreach($this->requestDoc->args as $argName=>$argVal) {
		if(in_array($argName,$this->byPassArgs)) {
				$this->serviceArgs[$argName] = $argVal;
				continue;
		}
		if(!array_key_exists($argName,$this->serviceDefinition['params'])) {
			throw new ServiceRoutingException("Argument " . $argName . " is not defined in the service definition.");
		}
      }
      return TRUE;
  }

  private function checkPaths() {
    $serviceRequestPath = $this->request->getURI()."/".$this->methodname;
    $execAllowed = FALSE;
    foreach($this->allowablePaths as $pattern=>$rights) {
	//remove any asterix from the pattern - they are unnecessary
	$pattern = str_replace("*","",$pattern);
	if($pattern == "/") {
		if(strpos($rights,"x")) $execAllowed=TRUE;
		continue;
	}
	if(strpos($serviceRequestPath,$pattern)) {
		if(strpos($rights,"x")) $execAllowed=TRUE;
		continue;
	}
    }
    return $execAllowed;
  }

  private function getWSPResponse($serviceResult) {
	$WSPResponse = new \stdclass;
	$WSPResponse->type = "jsonwsp/response";
	$WSPResponse->version = "1.0";
	$WSPResponse->servicename = $this->serviceName;
	$WSPResponse->methodname = $this->methodname;
	$WSPResponse->result = $serviceResult;
	if(property_exists($this->requestDoc,'mirror')) {
		$WSPResponse->reflection = $this->requestDoc->mirror;
	}
	return $WSPResponse;
  }

  public function resolve() {
	if(!strstr($this->methodname,"/")) {
		$serviceClass = $this->methodname;
	} else {
		$serviceClass = chr(92).str_replace("/",chr(92),$this->methodname);
	}
	$this->service = new $serviceClass($this->serviceArgs,$this->request,$this->response,$this->allowablePaths);
	$serviceResult = $this->service->run();
	return $this->getWSPResponse($serviceResult);
  }
  
}

?>
