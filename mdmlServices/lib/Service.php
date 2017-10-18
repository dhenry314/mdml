<?php

namespace mdml;

class LoggingException extends \Exception{};
class ServiceException extends \Exception{};
class RecordException extends ServiceException {
	
	// logged: 0 if not logged; 1 if logged
	var $logged = 0; 
	
	function __construct($msg,$errData) {
		if(array_key_exists('loggingServiceURI',$errData)) {
			try {
				$this->writeToLog($msg,$errData);
			} catch(LoggingException $e) {
				throw new ServiceException($e->getMessage());
			}
			$this->status = 1;
		} else {
			throw new ServiceException($msg);
		}
	}
	
	private function writeToLog($msg,$errData) {
		$data = array(
			'mdml:sourceURI'=>$errData['mdml:sourceURI'],
			'mdml:originURI'=>$errData['mdml:originURI'],
			'Message'=>$msg,
			'tag'=>$errData['loggingTag']
		);
		if(array_key_exists('LogLevel',$errData)) {
			$data['LogLevel'] = $errData['LogLevel'];  
		}
		try {
			Utils::postToURL($errData['loggingServiceURI'],$data,$errData['jwt']);
		} catch(\Exception $e) {
			throw new LoggingException("Could not write to log. ERROR: " . $e->getMessage());
		}
	  return TRUE;
  }

}

class Service {

  var $serviceArgs = array();
  var $loggingServiceURI;
  var $loggingTag;
  var $errors=array();
  var $http_method;
  var $request;
  var $queryStr;
  var $response;
  var $allowablePaths;
  var $requestDoc;
  var $jwt;

  function __construct($serviceArgs,$request,$response,$allowablePaths) {
      $this->serviceArgs = $serviceArgs;
      if(array_key_exists('mdml:loggingServiceURI',$this->serviceArgs)) {
		$this->loggingServiceURI=$this->serviceArgs['mdml:loggingServiceURI'];
		if(!array_key_exists('mdml:loggingTag',$this->serviceArgs)) {
			throw new LoggingException("An mdml:loggingTag is required when there is an mdml:loggingServiceURI");
		} else {
			$this->loggingTag = $this->serviceArgs['mdml:loggingTag'];
		}
	  }
      $this->http_method = $request->getMethod();
      $this->request = $request;
      $this->queryStr = $request->getUri()->getQuery();
      $this->response = $response;
      $this->allowablePaths = $allowablePaths;
      foreach($request->getHeader("Authorization") as $header) {
        $test = trim(strtolower($header));
        if(substr($test,0,6) == 'bearer') {
                $auth_parts = explode(' ',$header);
                $this->jwt = array_pop($auth_parts);
        }
      }
  }

  public function run() {
         return $this->response;
  }
  
  protected function getErrorData($sourceURI,$originURI,$level='INFO') {
	  $errData = array();
	  $errData['mdml:sourceURI'] = $sourceURI;
	  $errData['mdml:originURI'] = $originURI;
	  $errData['LogLevel'] = $level;
	  $errData['jwt'] = $this->jwt;
	  if($this->loggingServiceURI) {
			$errData['loggingServiceURI'] = $this->loggingServiceURI;
			$errData['loggingTag'] = $this->loggingTag;
	  }
	  return $errData;
  }
  
  public function handleResponse($response) {
		//response MUST be either an object or an array
		$type = gettype($response);
		switch($type) {
				case 'object':
				case 'array':
					return $response;
				break;
				case 'string':
					//MUST be a json string
					if($this->checkJSON($response,FALSE)) {
						return json_decode($response);
					} else {
						//wrap response in a json error
						$response = '{"ERROR": {"type":"UNKNOWN CONTENT RETURNED","content":"'. json_encode($response).'"}}';
						return json_decode($response);
					}
				break;
				default:
					throw new InvalidJSONException("Unknown response type: " . $type);
					return FALSE;
				
		}
   }
   
   public function validateBySchema($doc,$schemaPath) {
	$dereferencer  = new \League\JsonReference\Dereferencer();
	$schema = $dereferencer->dereference($schemaPath);
	$validator = new \League\JsonGuard\Validator((object)$doc, $schema);
	if ($validator->fails()) {
		$errors = $validator->errors();
		$errorObj = array_shift($errors);
		$errorMsg = $errorObj->getMessage();
		return $errorMsg;
	} else {
		return true;
	}
	  
  }
  
  public function URLExists($url) {
	$file_headers = @get_headers($url);
	if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
		return FALSE;
	}
	return TRUE;
  }
  
}

?>
