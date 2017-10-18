<?php

class RESTRequestException extends \Exception{};

class RESTRequest
{
	public $response;
	public $code;
	private $handle;
	private $session;
	private $curlopts;
	private $url;

	public function __construct()
	{
		$this->handle = curl_init();
		$cookiejar = tempnam(sys_get_temp_dir(), 'session');

		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
		);
		
		$this->curlopts = array(
			CURLOPT_HTTPHEADER=>$headers,
			CURLINFO_HEADER_OUT=>true,
			CURLOPT_RETURNTRANSFER=>true,
			CURLOPT_COOKIEJAR=>$cookiejar,
			CURLOPT_COOKIEFILE=>$cookiejar,
			CURLOPT_SSL_VERIFYHOST=>false,
			CURLOPT_SSL_VERIFYPEER=>false,
			CURLOPT_FAILONERROR=>true
		);
	}

	/**
	 * @return (object) cURL handle
	 */
	public function getHandle()
	{
		return $this->handle;
	}

	/**
	 * @param (array) $headers Array of header values
	 * @return (array) Headers
	 */
	public function setHeaders($headers)
	{
		//check for Authorization header and handle it
		foreach($headers as $header) {
				if(strstr($header,"Authorization") || strstr($header,"authorization")) {
						 $this->curlopts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
				}
		}
		$this->curlopts[CURLOPT_HTTPHEADER] = $headers;

		curl_setopt_array($this->handle, $this->curlopts);

		$this->getHeaders();
	}

	/**
	 * @return (array) Headers
	 */
	public function getHeaders()
	{
		return $this->curlopts[CURLOPT_HTTPHEADER];
	}

	/**
	 * @param (string) $method GET/PUT/POST/DELETE
	 * @param (string) $url Request URL
	 * @param (json) $data JSON data (optional)
	 * @return (boolean) TRUE
	 */
	public function setUp($method, $url, $data='')
	{
		$this->url = $url;
		curl_setopt_array($this->handle, $this->curlopts);
		curl_setopt($this->handle, CURLOPT_URL, $url);

		if ($this->session)
			curl_setopt($this->handle, CURLOPT_COOKIE, $this->session);

		switch(strtoupper($method)) {
			case 'GET':
				break;

			case 'PUT':
			case 'POST':
				curl_setopt($this->handle, CURLOPT_POST, true);
				curl_setopt($this->handle, CURLOPT_POSTFIELDS, json_encode($data));
				break;


			case 'DELETE':
				curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;
		}

		return true;
	}

	/**
	 * @return (boolean) Success of HTTP request
	 */
	public function send()
	{
		$this->response = curl_exec($this->handle);
		$this->code = curl_getinfo($this->handle, CURLINFO_HTTP_CODE);
		
		$curl_errno = curl_errno($this->handle);
		$curl_errmsg = curl_error($this->handle);
		if($curl_errno) {
				throw new RESTRequestException(
								"HTTP_RESPONSE_CODE: " . $this->code 
								. " CURL_ERROR_NUMBER: ". $curl_errno
								. " MSG: " . $curl_errmsg
								. " URL: " . $this->url 
							);
		}

		if (!$this->session)
			$this->session = session_id() .'='. session_id() .'; path=' . session_save_path();

		session_write_close();
		
		curl_close($this->handle);
		
		return !!$this->response;
	}
}
