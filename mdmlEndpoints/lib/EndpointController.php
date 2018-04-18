<?php

class EndpointRoutingException extends Exception{};
class InvalidJSONException extends Exception{};

class EndpointController {

  var $http_method;
  var $config;
  var $request;
  var $RESTService;
  var $ResourceSyncService;
  var $response;
  var $args = array();
  var $requestedPath;
  var $paths = array();
  var $allowablePaths;
  var $postedRaw;
  var $postedData;
  var $requestDoc;
  var $jwt;

  function __construct($request,$response,$allowablePaths,$args=array()) {
      $config = include __DIR__ . '/../config.php';
      $this->config = $config;
      $this->http_method = $request->getMethod();
      $this->request = $request;
      $postedRaw = file_get_contents('php://input');
      if($postedRaw) {
        $this->postedRaw = $postedRaw;
        $this->postedData = \Utils::jsonToObj($postedRaw);
        $this->requestDoc = \Utils::arrayToObj($this->postedData);
      }
      $this->queryStr = $request->getUri()->getQuery();
      $this->RESTService = new RESTService($args['path'],$this->http_method,$this->postedData,$this->queryStr);
      $this->ResourceSyncService = new ResourceSyncService();
      $this->RESTService->setResourceSyncService($this->ResourceSyncService);
      foreach($request->getHeader("Authorization") as $header) {
	$test = trim(strtolower($header));
	if(substr($test,0,6) == 'bearer') {
		$auth_parts = explode(' ',$header);
		$this->jwt = array_pop($auth_parts);
	}
      }
      $this->response = $response;
      $this->args = $args;
      $this->allowablePaths = $allowablePaths;
      $this->requestedPath = $args['path'];
      if(!$this->checkPaths()) {
        throw new EndpointRoutingException("Path requested is not allowed.");
      }
      $paths = explode("/",$args['path']);
      $this->namespace = array_shift($paths);
      $this->paths = $paths;
  }

  private function checkPaths() {
	$requestPath = $this->request->getURI();
	$allowed=FALSE;
	foreach($this->allowablePaths as $pattern=>$rights) {
     	   //remove any asterix from the pattern - they are unnecessary
		$patternAllowed=FALSE;
        	$pattern = str_replace("*","",$pattern);
        	if($pattern == "/") {
			$patternAllowed=TRUE;
        	} elseif(strpos($requestPath,$pattern)) {
			$patternAllowed=TRUE;
        	}
		if($patternAllowed) {
			switch($this->http_method) {
				case 'PUT':
				case 'POST':
				case 'DELETE':
					if(strstr($rights,'w')) {
						return TRUE;
					}
				break;
				case 'GET':
					if(strstr($rights,'r')) {
                                                return TRUE;
                                        }
				break;
			}

		}
    	}
	return $allowed;
  }

  public function resolve() {
    	parse_str($this->queryStr, $queryParams);
    	$format = 'xml';
    	if(array_key_exists('format',$queryParams)) {
    		$format = $queryParams['format'];
    	}
    	//check for resourceSync specific requests
    	if(strlen($this->requestedPath)==0) {
    		return $this->ResourceSyncService->getSitemap('json');
    	} elseif(in_array('_find',$this->paths)) {
    		return $this->RESTService->findRecord($this->requestDoc);
    	} elseif($this->requestedPath == 'sitemap.xml') {
        return $this->ResourceSyncService->getSitemap($format);
      } elseif(strstr($this->requestedPath,'resourcelist.xml')) {
        return $this->ResourceSyncService->getResourceList($this->requestedPath,$format,$queryParams);
      } elseif(strstr($this->requestedPath, 'changelist.xml')) {
    		parse_str($this->queryStr, $queryParams);
      	return $this->ResourceSyncService->getChangelist($this->requestedPath,$queryParams);
    	} elseif(strstr($this->requestedPath, 'info.json')) {
    		return $this->ResourceSyncService->getInfo($this->requestedPath);
    	} else {
    		//otherwise run a REST request
    		$this->RESTService->run();
    		return $this->RESTService->getResponse();
	     }
  }

}

?>
