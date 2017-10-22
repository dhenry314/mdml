<?php

class StorageConnectionException extends \MongoDB\Exception\RuntimeException{};
class StorageQueryException extends \MongoDB\Exception\RuntimeException{};
class StorageDeleteException extends \MongoDB\Exception\RuntimeException{};
class StorageInsertException extends \MongoDB\Exception\RuntimeException{};
class StorageUpdateException extends \MongoDB\Exception\RuntimeException{};

class MongoStorage implements iStorage {

  var $config;
  var $basePath;
  var $mongoDB;
  var $mongoColl;
  var $maxResults = 20;
  var $resultCount;

  public function __construct() {
    $config = include __DIR__ . '/../config.php';
    $this->config = $config;
    $this->basePath = $this->config['HTTP_PROTOCOL']."//". $_SERVER['SERVER_NAME'] . $this->config['BASE_PATH'];
    if(!array_key_exists('mongo',$this->config)) {
		throw new StorageConnectionException("No mongo connection in configuration!");
	}
    if(array_key_exists('connect_string',$this->config['mongo'])) {
			$connecting_string = $this->config['mongo']['connect_string'];
	} else {
			$connecting_string =  sprintf('mongodb://%s:%d/',
                             $this->config['mongo']['host'],
                             $this->config['mongo']['port']);
    }
    if(array_key_exists('connectOptions',$config['mongo'])) {
		$options = $config['mongo']['connectOptions'];
    } else {
    	$options = array();
    }
    if(array_key_exists('pw', $config['mongo']) && array_key_exists('user', $config['mongo'])) {
	      $options['username'] = $config['mongo']['user'];
	      $options['password'] = $config['mongo']['pw'];
    }
    if(array_key_exists('MAX_RESULTS', $config)) {
      $this->maxResults = $config['MAX_RESULTS'];
    }
    try {
      $connection=  new \MongoDB\Client($connecting_string,$options);
      $this->mongoDB = $connection->selectDatabase($config['mongo']['database']);
      $this->mongoColl = $this->mongoDB->selectCollection('mdml');
    } catch (\MongoCursorException $e) {
      throw new StorageConnectionException($e->getMessage());
    }
  }
  
  public function saveDocument($doc,$loc){
	return $this->upsert($doc,$loc);
  }

  public function getDocument($loc) {
	  $id = $this->basePath.$loc;
      //handle entities
      if(strstr($id,'&amp;')) {
			$id = str_replace('&amp;','&',$id);
      }
      $query = array('@id'=>$id);
      try {
        $doc = $this->mongoColl->findOne($query);
      } catch (\Exception $e) {
        throw new StorageQueryException($e->getMessage(),$e->getCode());
      }
      if(!$doc) {
			return FALSE;
      }
      $json = json_encode( $doc->getArrayCopy() );
      return $json;
  }

  public function removeDocument($loc) {
	$id = $this->basePath.$loc;
    $query = array('@id'=>$fullID);
    return $this->deleteByQuery($query);
  }
  
  public function upsert($doc,$loc) {
	$id = $this->basePath.$loc;
	$doc['@id'] = $id;
	$filter = array('@id'=>$id);
	if($existing = $this->findOne($filter)) {
		return $this->updateDocument($doc,$id);
	} else {
		return $this->insertDocument($doc,$id);
	}
  }
  
  public function findOne($query) {
      try {
        $doc = $this->mongoColl->findOne($query);
      } catch (\Exception $e) {
        throw new StorageQueryException($e->getMessage(),$e->getCode());
      }
      if(!is_object($doc)) return FALSE;
      $json = json_encode( $doc->getArrayCopy() );
      return $json;
  }
  
  public function insertDocument($doc,$loc) {
	$id = $this->basePath.$loc;
    if(!array_key_exists('@id',$doc)) {
		$doc['@id'] = $id;
	}
	if(is_array($doc)) {
		$doc = \Utils::arrayToObj($doc);
    }
    try {
      $result = $this->mongoColl->insertOne($doc);
    } catch (\Exception $e) {
      throw new StorageInsertException($e->getMessage(),$e->getCode());
    }
    return $result;
  }

  public function updateDocument($doc,$loc) {
	$id = $this->basePath.$loc;
    $idQuery = array('@id'=>$id);
    try {
      $result = $this->mongoColl->replaceOne($idQuery,$doc);
    } catch (\Exception $e) {
      throw new StorageUpdateException($e->getMessage(),$e->getCode());
    }
    return $result;
  }

  public function getCursor($query,$options) {
	try {
      		$cursor = $this->mongoColl->find($query,$options);
    	} catch (\Exception $e) {
      		throw new StorageQueryException("Could not get results. ERRORS: " . $e->getMessage(),$e->getCode());
    	}
	return $cursor;
  }

   public function getResultsCursor($query,$paging=NULL,$fields=array()) {
    $options = array();
    if($paging['count']) {
      if($paging['count'] > $this->maxResults) {
        throw new StorageQueryException('Count value given exceed maximum count: ' . $this->maxResults);
      }
      $options['limit'] = (int)$paging['count'];
    } else {
      $options['limit'] = (int)$this->maxResults;
    }
    if($paging['offset']) {
      $options['skip'] = (int)$paging['offset'];
    }
    foreach($fields as $field) {
	$options[$field] = 1;
    }
    try {
      $this->resultCount = $this->mongoColl->count($query);
      $cursor = $this->mongoColl->find($query,$options);
    } catch (\Exception $e) {
      throw new StorageQueryException("Could not get results. ERRORS: " . $e->getMessage(),$e->getCode());
    }
    return $this->getCursor($query,$options);
  }


  public function getResults($query,$paging=NULL) {
    $options = array();
    if($paging['count']) {
      if($paging['count'] > $this->maxResults) {
        throw new StorageQueryException('Count value given exceed maximum count: ' . $this->maxResults);
      }
      $options['limit'] = $paging['count'];
    } else {
      $options['limit'] = $this->maxResults;
    }
    if($paging['offset']) {
      $options['skip'] = $paging['offset'];
    }
    try {
      $this->resultCount = $this->mongoColl->count($query);
      $cursor = $this->mongoColl->find($query,$options);
    } catch (\Exception $e) {
      throw new StorageQueryException("Could not get results. ERRORS: " . $e->getMessage(),$e->getCode());
    }
    if($cursor = $this->getCursor($query,$options)) {
	$results = array();
	foreach($cursor as $doc) {
		$results[] = $doc->getArrayCopy();
	}
        return $results;
    }
    return array();
  }

  public function getDistinct($field,$query=array(),$options=array()) {
    try {
	if(count($query)>0) {
      		$cursor = $this->mongoColl->distinct($field,$query,$options);
	} else {
		$cursor = $this->mongoColl->distinct($field);
	}
    } catch (\Exception $e) {
      throw new StorageQueryException($e->getMessage(),$e->getCode());
    }
    if($cursor) {
                $results = array();
                foreach($cursor as $doc) {
                        $results[] = $doc;
                }
        return $results;
    }
    return array();
  }

  public function getCount() {
	$resultCount = 0;
	try {
      		$resultCount = $this->mongoColl->count($query);
    	} catch (\Exception $e) {
      		throw new StorageQueryException("Could not get count. ERRORS: " . $e->getMessage(),$e->getCode());
    	}
	return $resultCount;
  }

  public function deleteByQuery($query) {
    try {
      $result = $this->mongoColl->deleteMany($query);
    } catch (\Exception $e) {
      throw new StorageDeleteException($e->getMessage(),$e->getCode());
    }
    header("HTTP/1.0 204 No Content");
    exit;
  }

}

?>
