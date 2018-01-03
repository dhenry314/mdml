<?php

namespace mdml;

class InvalidFileCache extends \InvalidArgumentException{};
 
class fileCache extends Service {

	var $cacheBase;
	var $pathReplacement;
	var $cacheName;
	var $cachePath;
	var $sourceDoc;
	var $uri;

	/**
	* constructor
	*/
	function __construct($serviceArgs,$request,$response,$allowablePaths) {
		parent::__construct($serviceArgs,$request,$response,$allowablePaths);
		if(!array_key_exists('mdml:sourceURI',$serviceArgs)) {
			throw new InvalidFileCache("No mdml:sourceURI defined.");
		}
		if(!array_key_exists('cacheName',$serviceArgs)) {
			throw new InvalidFileCache("No cacheName provided!");
		}
		if(!array_key_exists('pathReplacement',$serviceArgs)) {
			throw new InvalidFileCache("No pathReplacement provided!");
		}
		$this->cacheName = $serviceArgs['cacheName'];
		$this->pathReplacement = $serviceArgs['pathReplacement'];
		$this->sourceURI = $serviceArgs['mdml:sourceURI'];
		$this->serviceClient = new ServiceClient($this->jwt);
		if(!is_writable($this->cacheBase)) {
			throw new InvalidFileCache("cacheBase is not writable!");
		}
		if(!$docResult = $this->serviceClient->get($this->sourceURI)) {
                         throw new InvalidFileCache(
					"Could not load input document from given sourceURI: "
                                         . $this->serviceArgs['mdml:sourceURI']
					);
                }
                if(is_object($docResult)) {
                        $this->sourceDoc = $docResult->{'mdml:payload'};
                } elseif(is_array($docResult)) {
                        $this->sourceDoc = $docResult['mdml:payload'];
		}
	}

	public function run() {
		if($this->saveToCache()) {
			$this->response = array(
				"content"=>"Successfully cached " . $this->sourceURI 
			);
		} else {
			throw new InvalidFileCache("Could not save to cache.  Unknown Error.");
		}
		return true;
	}

	protected function saveToCache($uri=NULL,$record=NULL) {
		if(!$uri) {
			$uri = $this->uri;
		}
		if(!$record) {
			$record = $this->sourceDoc;
		}
		//remove trailing slash
		if(substr($uri,-1,1) == "/") {
		       $uri = substr($uri,0,-1);
		}
		$path = str_replace($this->pathReplacement,$this->cacheName."/",$uri);
		$localPaths = explode("/",$path);
		$fileName = array_pop($localPaths) . ".json";
		$localFolder = implode("/",$localPaths);
		$folder = $this->cacheBase.$localFolder;
		if(!is_dir($folder)) {
			if (!mkdir($folder, 0777, true)) {
 			   throw new InvalidFileCache('Failed to create cache folder.');
			}
		}
		$path = $folder."/".$fileName;
		$sourceJson = Utils::safe_json_encode($record);
		if (!$handle = fopen($path, 'w')) {
         		throw new InvalidFileCache("Cannot open file ($path)");
    		}
    		if (fwrite($handle, $sourceJson) === FALSE) {
        		throw new InvalidFileCache("Cannot write to file ($path)");
    		}
		return true;
	}
	
}

