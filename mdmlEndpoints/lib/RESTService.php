<?php

class RESTServiceException extends \Exception{};
class RESTServiceValidationException extends \Exception{};

class RESTService {

  var $path;
  var $postedData;
  var $method;
  var $recordID;
  var $storage;
  var $paging = array('offset'=>0,'count'=>20);
  var $filter = array();
  var $deleteConfirmation;
  var $config;
  var $response;
  var $resourceSyncService;

  public function __construct($path,$httpMethod='GET',$postedData=NULL,$queryStr=NULL) {
	$config = include __DIR__ . '/../config.php';
	$this->config = $config;
	$this->path = trim($path);
	//check for resourceSync specific requests
	if($this->path == 'sitemap.xml') {
		$this->method = 'getSitemap';
	} elseif(strstr($this->path,'resourcelist.xml')) {
		$this->method = 'getResourceList';
	} elseif(strstr($this->path, 'changelist.xml')) {
		$this->method = 'getChangeList';
	}
    $storageClass = chr(92).$config['storageClass'];
    $this->storage = new $storageClass();
	if($postedData) $this->postedData = $postedData;
	if($queryStr) {
		parse_str($queryStr,$params);
		if(array_key_exists('offset',$params)) {
			$this->paging['offset'] = $params['offset'];
		}
		if(array_key_exists('count',$params)) {
			$this->paging['count'] = $params['count'];
		}
		if(strstr($queryStr,'filter=')) {
			$qParts = explode('filter=',$queryStr);
			$filterQ = array_pop($qParts); 
			//parse out field and value
			$fParts = explode(":",$filterQ);
			$this->filter['field'] = array_shift($fParts);
			$fRemainder = implode(":",$fParts);
			if(substr($fRemainder,0,1)=='"') {
				//remove quotes if it's wrapped in quotes
				$fRemainder = str_replace('"','',$fRemainder);
			} elseif(substr($fRemainder,0,3)=='%22') {
				$fRemainder = str_replace('%22','',$fRemainder);
			}
			$fRemainder = urldecode($fRemainder);
			$this->filter['value'] = $fRemainder;
		}
	}
	//parse recordID from path
	$pathParts = explode("/",$path);
	$lastPart = trim(array_pop($pathParts));
	if(ctype_digit($lastPart)) {
		$this->recordID = $lastPart;
	}
    //initialize method
    if(!$this->method) {
		switch($httpMethod) {
			case 'GET':
				if($this->recordID) {
					$this->method = 'getRecord';
				} else {
					$this->method = 'getListing';
				}
			break;	
			case 'POST':
			case 'PUT':
				try{
					$this->validatePayload();
				} catch(RESTServiceValidationException $e) {
					throw new RESTServiceException($e->getMessage());
				}
				if($this->postedData) {
					if($this->recordID) {
						$this->method = 'updateRecord';
					} else {
						$this->method = 'createRecord';
					}
				}
			break;
			case 'DELETE':
				if($this->recordID) {
					$this->method = 'deleteRecord';
				} else {
					$this->method = 'deleteEndpoint';
				}
			break;
		}
		if(!$this->method) {
				throw new RestServiceException("Could not determine method.");
		}
    }
  }

  public function validatePayload() {
	if(array_key_exists('mdml:payloadSchema',$this->postedData)) {
		if(!\Utils::urlExists($this->postedData['mdml:payloadSchema'])) {
			throw new RESTServiceValidationException("payloadSchema path does not exist.");
		}
		$doc = $this->postedData['mdml:payload'];
		$schemaPath = $this->postedData['mdml:payloadSchema'];
		try{
			\Utils::validateBySchema($doc,$schemaPath);
		} catch(\Exception $e) {
			throw new RESTServiceValidationException("Could not validate payload: " . $e->getMessage());
		}
	}
	return TRUE;
  }

  public function setResourceSyncService($rs) {
	$this->resourceSyncService = $rs;
  }

  public function run() {
	  $methodName = $this->method;
	  return $this->$methodName();
  }

  public function getResponse() {
	 return $this->response;  
  }

  public function setError($code) {
		$response = new \stdclass();
		$response->errorCode = $code;
		$this->response = $response;
  }

  public function getRecord() {
	if($resource = $this->resourceSyncService->getResource($this->path)) {
		$doc = $this->storage->getDocument($resource['path']."/".$resource['ID']);
		$this->response = $doc;
	} else {
		$this->setError(404);
        	return FALSE;
	}
        return TRUE;
  }

  public function findRecord($query) {
  	$paging = array();
	if(property_exists($query,'offset')) {
		$paging['offset'] = $query->offset;
	}
	if(property_exists($query,'count')) {
		$paging['count'] = $query->count;
	}

  	if($results = $this->storage->getResults($query->query,$paging)) {
	      if(count($results)==1) {
	      	$this->response = $results[0];
	      } else {
		$this->response = $results;
	      }
	} else {
              $this->setError(404);
  	      return FALSE;
        }
	return $this->response;
  }

  public function createRecord() {
	$posted = $this->postedData;
	$required = array("mdml:originURI","mdml:sourceURI","mdml:payload");
	foreach($required as $reqField) {
		if(!array_key_exists($reqField,$posted)) {
			throw new RESTServiceException("Missing required field: " . $reqField);
		}
	}
    	$hash = \Utils::hashFromContents($posted['mdml:payload']);
	if($loc = $this->resourceSyncService->saveResource($this->path,$posted['mdml:originURI'],$posted['mdml:sourceURI'],$hash)) {
		$this->storage->upsert($posted,$loc);
	}
	$this->response = $posted;
  }

  public function updateRecord() {
	$posted = $this->postedData;
	$required = array("mdml:originURI","mdml:sourceURI","mdml:payload");
	foreach($required as $reqField) {
		if(!array_key_exists($reqField,$posted)) {
			throw new RESTServiceException("Missing required field: " . $reqField);
		}
	}
    $hash = \Utils::hashFromContents($posted['mdml:payload']);
	if($loc = $this->resourceSyncService->saveResource($this->path,$posted['mdml:originURI'],$posted['mdml:sourceURI'],$hash)) {
		$this->storage->updateDocument($posted,$loc);
	}
	$this->response = $posted;
  }

  public function deleteRecord($path=NULL) {
	if(!$path) {
		$path = $this->path;
	}
	if(!$resource = $this->resourceSyncService->getResource($path)) {
		throw new RESTServiceException("No resource to delete.");
	}
	$hash = $resource['hash'];
	$doc = $this->storage->getDocument($path);
	$doc['mdml:status'] = 'deleted';
	$pathParts = explode("/",$path);
	$parentPath = implode("/",$pathParts);
	$recordID = array_pop($pathParts);
	if($loc = $this->resourceSyncService->saveResource(
						$parentPath,
						$doc['mdml:originURI'],
						$doc['mdml:sourceURI'],
						$hash,
						'deleted',
						$path)
	) {
		$this->storage->updateDocument($doc,$loc);
	}
	$this->setMessage("Resource " . $loc . " has been marked deleted.");
  }

  public function getListing($params=array()) {
  	return $this->resourceSyncService->getResourcelist($this->path,'json',$this->paging,$this->filter);
  }

  public function post() {
    $this->createRecord();
  }

  public function put() {
    return $this->post();
  }

  public function deleteEndpoint() {
	//mark all records with given path as deleted
	$urls = $this->resourceSyncService->getPathResources($this->path);
	foreach($urls as $url) {
		$path = $this->resourceSyncService->normalizePath($url['loc']);
		$this->deleteRecord($path);
	}
	$this->setMessage("All records in endpoint: " . $this->path . " have been marked deleted.");
  }

  public function setMessage($msg) {
	$response = array();
	$response['mdml:message'] = $msg;
	$this->response = $response;
  }

}

?>
