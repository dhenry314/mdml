<?php

namespace mdml;

class ServiceException extends \Exception{};
//class InvalidJSONException extends \Exception{};

class RecordException extends ServiceException {

       function __construct($msg,$errData) {
               $err = Utils::safe_json_encode($errData);
               throw new ServiceException($msg. " " . $err);
       }

}

class Service {

  var $serviceArgs = array();
  var $errors=array();
  var $http_method;
  var $request;
  var $queryStr;
  var $response;
  var $allowablePaths;
  var $requestDoc;
  var $jwt;
  var $serviceClient;

  function __construct($serviceArgs,$request,$response,$allowablePaths) {
      $this->serviceArgs = $serviceArgs;
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
      $this->serviceClient = new ServiceClient($this->jwt);
  }

  public function run() {
         $this->response = $this->handleResponse($this->response);
         return true;
  }

  protected function getErrorData($sourceURI,$originURI,$level='INFO') {
	  $errData = array();
	  $errData['mdml:sourceURI'] = $sourceURI;
	  $errData['mdml:originURI'] = $originURI;
	  $errData['LogLevel'] = $level;
	  $errData['jwt'] = $this->jwt;
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

  public function getDocument($uri) {
  	if(!$docResult = $this->serviceClient->get($uri)) {
                  throw new ServiceException("Could not load input document from given sourceURI: "
                                   . $uri);
        }
	return $docResult;
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
