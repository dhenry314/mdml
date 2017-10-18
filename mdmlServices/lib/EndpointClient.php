<?php

namespace mdml;

class EndpointClientException extends \Exception{};

class EndpointClient extends Service {

	/**
	* __construct
	* 
	* The constructor takes $request and $response from the SLIM framework plus an $allowablePaths array
	* and initializes the service.
	*
	* @param object $request a SLIM Request object
	* @param object $response a SLIM Response object
	* @param array $allowablePaths an array of paths that may be called -- restricted by the JWT token.
	*
	* @return EndpointClient
	*/
	function __construct($serviceArgs,$request,$response,$allowablePaths) {
		parent::__construct($serviceArgs,$request,$response,$allowablePaths);
	}

	/**
	* callInit
	*
	* This method initializes a CURL request with the given endpoint (url)
	* 
	* @param string $endpoint The endpoint may be any url served by and MDML endpoint
	* 
	* @return object $ch  a CURL handle
	*/
	private function callInit($endpoint) {
		 if(!$endpoint) {
                        throw new EndpointClientException("No endpoint path is defined.");
                }
                $authorization = "Authorization: Bearer ".$this->jwt;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $endpoint);
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
                if(curl_errno($ch)){
                   throw new EndpointClientException("Curl error: "  . curl_error($ch));
                }
                curl_close($ch);
                return json_decode($result);
	}

	/**
	* addFileName
	*
	* This method takes an endpoint and concatenates it with a filename avoiding duplicated slashes
	*
	* @param string $endpoint a base endpoint url
	* @param string $filename a filename to be concatenated to the base endpoint url
	* 
	* @return string
	*/
	private function addFileName($endpoint,$filename) {
		if(substr($endpoint,-1,1) != "/") {
			$endpoint .= "/";
		}
		if(substr($filename,0,1) == "/") {
			$filename = substr($filename,1);
		}
		return $endpoint.$filename;
	}

	/**
	* getData
	*
	* This method takes a url and optional parameters, calls a GET method with curl, and returns the result as JSON
	*
	* @param string $url
	* @param array $params an optional array of url parameters
	*
	* @return string a JSON result
	*/
	private function getData($url,$params=array()) {
		$query_str = http_build_query($params);
		$url .= "?".$query_str;
		$ch = $this->callInit($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		return $this->call($ch);
	}

	/**
        * post
        *
        * This method takes data to post and an endpoint url, calls a POST method with curl, and returns the result as JSON
        *
        * @param mixed $data
        * @param string $endpoint an MDML endpoint base url
        *
        * @return string a JSON result
        */
	public function post($data,$endpoint) {
		$json = Utils::safe_json_encode($data);
		$ch = $this->callInit($endpoint);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$json);
		return $this->call($ch);
	}

	/**
	* get
	*
	* An alias of getData
	*/
	public function get($url) {
		return $this->getData($url);
	}

	/**
        * delete 
        *
        * This method takes a url, calls a DELETE method with curl, and returns the result as JSON
        *
        * @param string $url
        *
        * @return string a JSON result
        */
	public function delete($url) {
		$ch = $this->callInit($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                return $this->call($ch);
	} 

	/**
        * changeList 
        *
        * This method takes an endpoint, format, from, and until, calls a GET method with curl, 
	* and returns the result as determined by format
        *
        * @param string $endpoint a base endpoint url
        * @param string $format and optional format - defaults to JSON
	* @param string $from an optional date string in ISO8601 format that is the start of a date range to query
	* @param string $until an optional date string in ISO8601 format or the end of a date range to query
        *
        * @return string a JSON result
        */
	public function changeList($endpoint,$format=json,$from=NULL,$until=NULL) {
		$url = $this->addFileName($endpoint,"changelist.xml");
		//set params
		$params = array();
		$params['format'] = $format;
		if($from) $params['from'] = $from;
		if($until) $params['until'] = $until;
		return $this->getData($url,$params);
	}

	/**
        * resourceList 
        *
        * This method takes an endpoint and format, calls a GET method with curl, 
        * and returns the result as determined by format
        *
        * @param string $endpoint a base endpoint url
        * @param string $format and optional format - defaults to JSON
        *
        * @return string a JSON result
        */
	public function resourceList($endpoint,$format=json) {
		$url = $this->addFileName($endpoint,"resourcelist.xml");
                //set params
                $params = array();
                $params['format'] = $format;
                return $this->getData($url,$params);
	}

	/**
        * info 
        *
        * This method takes an endpoint, calls a GET method with curl, 
        * and returns the result as determined by format
        *
        * @param string $endpoint a base endpoint url
        *
        * @return string a JSON result
        */
	public function info($endpoint) {
		$url = $this->addFileName($endpoint,"info.json");
                return $this->getData($url,$params);
	}

}

?>
