<?php

namespace mdml;

class ServiceClientException extends \Exception{};

class ServiceClient {

    protected $jwt;
	
	function __construct($jwt=NULL,$auth=array()) {
		if($jwt) {
			$this->jwt = trim($jwt); 
		} elseif(array_key_exists('username',$auth) 
		  && array_key_exists('password',$auth)
		  && array_key_exists('loginService',$auth)) 
		  {
			$fullLoginUrl = $auth['loginService']."?username=".$auth['username']."&password=".$auth['password'];
			if(!$authContents = Utils::getFileContents($fullLoginUrl)) {
				throw new ServiceClientException("No login url found at " . $fullLoginUrl);
			}
			$authResult = Utils::jsonToObj($authContents);
			$this->jwt = $authResult['JWT'];
		}
	}

	/**
	* callInit
	*
	* This method initializes a CURL request with the given url
	* 
	* @param string $url 
	* 
	* @return object $ch  a CURL handle
	*/
	private function callInit($url) {
		 if(!$url) {
			throw new ServiceClientException("No url given.");
         }
         if(!Utils::urlExists($url)) {
			throw new ServiceClientException("URL is not found."); 
		 }
         $authorization = "Authorization: Bearer ".$this->jwt;
         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, $url);
         curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	     return $ch;
	}

	/**
	* call
	*
	* This method takes a CURL handle, executes it, and returns the result as json
	*
	* @param object $ch a CURL handle
	*
	* @throws EndpointClientException if CURL returns an error
	*
  	* @returns string a JSON encoded result 
	*/
	private function call($ch) {
		$result = curl_exec($ch);
		if(!$result) {
			throw new ServiceClientException("No result returned from curl call.");
		}
        if(curl_errno($ch)){
            throw new ServiceClientException("Curl error: "  . curl_error($ch));
        }
        curl_close($ch);
        $contents = NULL;
        try {
			$contents = Utils::jsonToObj($result);
		} catch (\Exception $e) {
			throw new ServiceClientException("Could not parse JSON from service call. " . $e->getMessage());
		}
		return $contents;
	}

	private function wrapParams($methodname,$args=NULL,$mirror=NULL) {
		$request = new \stdclass;
		$request->type = "jsonwsp/request";
                $request->version = "1.0";
	        $request->methodname = $methodname;
		//$request->methodname = "foobar";
		if($args) {
                        $args = Utils::arrayToObj($args);
	        	$request->args = $args;		
		}
		if($mirror) {
			$mirror = Utils::arrayToObj($mirror);
			$request->mirror = $mirror;
		}
		return Utils::safe_json_encode($request);
	}

	public function callService($url,$methodname,$args=array(),$mirror=array()) {
		//wrap params in a standard WSP request
		$jsonRequest = $this->wrapParams($methodname,$args,$mirror);
		try {
			$response = $this->post($jsonRequest,$url);
		} catch(\Exception $e) {
			throw new ServiceClientException("Could not post to ". $url. " with methodname: " . $methodname);
		}
		if(property_exists($response,'result')) {
			return $response->result;
		} elseif(property_exists($response,'type')) {
			if(strpos($response->type,'fault')) {
				throw new ServiceClientException("Fault when calling service at " . $url . ": " . $response->fault->string);
			}
		        throw new ServiceClientException("Unknown response from " . $url . " with methodname: " . $methodname);
		} else {
			throw new ServiceClientException("Unknown response from " . $url . " with methodname: " . $methodname);
		}
		return TRUE;
	}

	public function get($url) {
		$ch = $this->callInit($url);
                try {
                        $result = $this->call($ch);
                } catch(\Exception $e) {
                        throw new ServiceClientException("Failed to make CURL call. ERROR: " . $e->getMessage());
                }
                return $result;
	}

	/**
        * post
        *
        * This method takes data to post and an url, calls a POST method with curl, and returns the result as JSON
        *
        * @param mixed $data
        * @param string $url
        *
        * @return string a JSON result
        */
	public function post($json,$url) {
		$ch = $this->callInit($url);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$json);
		try {
			$result = $this->call($ch);
		} catch(\Exception $e) {
			throw new ServiceClientException("Failed to make CURL call. ERROR: " . $e->getMessage());
		}
		return $result;
	}

}

?>
