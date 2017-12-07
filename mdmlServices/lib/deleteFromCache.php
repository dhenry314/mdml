<?php

namespace mdml;

class deleteFromCache extends Service {

	var $cacheBase;
	var $pathReplacement;
	var $cacheName;
	var $deleted = array();

	/**
	* constructor
	*/
	function __construct($serviceArgs,$request,$response,$allowablePaths) {
		parent::__construct($serviceArgs,$request,$response,$allowablePaths);
		if(!array_key_exists('cacheName',$serviceArgs)) {
			throw new InvalidFileCache("No cacheName provided!");
		}
		if(!array_key_exists('pathReplacement',$serviceArgs)) {
			throw new InvalidFileCache("No pathReplacement provided!");
		}
		$this->cacheName = $serviceArgs['cacheName'];
		$this->pathReplacement = $serviceArgs['pathReplacement'];
		if(!is_writable($this->cacheBase)) {
			throw new InvalidFileCache("cacheBase is not writable!");
		}
	}

	public function run() {
		if($this->runDeletes()) {
				$this->response = array(
					"content"=>"Successfully removed old files from cache. ",
					"deleted"=>$this->deleted
				);
		} else {
				throw new ServiceException("Could not delete from cache. Unknown Error.");
		}
		return parent::run();
	}

  private function runDeletes() {
		$path = $this->cacheBase.$this->cacheName;
		$objects = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::SELF_FIRST);
		$n=0;
		foreach($objects as $name => $object){
			  if(substr($name,-5,5) != '.json') continue;
				$dayAgo = strtotime("-1 day");
				$mtime = filemtime($name);
				$timeDiff = $dayAgo - $mtime;
				if($timeDiff < 0) continue;
				$url = str_replace(".json","/",$name);
				$url = str_replace($this->cacheName,$this->pathReplacement,$url);
				$url = str_replace($this->cacheBase,'',$url);
				if(!\mdml\Utils::urlExists($url)) {
							unlink($name);
							$this->deleted[] = $url;
				}
		}
		return true;
	}

}
