<?php

class LoggingException extends \Exception{};

class LoggingService extends EndpointController{

  var $config;
  var $db;
  var $count=100;
  var $args=array();
  var $filter=array();
  var $response;
 

  public function __construct($request,$response,$allowablePaths,$args=array()) {
    $config = include __DIR__ . '/../config.php';
    $this->config = $config;
    try {
        $this->db = new PDO($config['db']['connectStr'], $config['db']['user'], $config['db']['pw']);
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        throw new LoggingException('Could not connect to database ' . $e->getMessage());
    }
    $this->args = $args;
    parent::__construct($request,$response,$allowablePaths,$args);
    if(strstr($this->queryStr,'filter=')) {
		$qParts = explode('filter=',$this->queryStr);
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
  
  public function setError($code) {
		$response = new \stdclass();
		$response->errorCode = $code;
		$this->response = $response;
  }
  
  public function resolve() {
	    $content=NULL;
		$path = $this->args['path'];
		$id = NULL;
		$remainder = str_replace('loggingService','',$path);
		if(strlen($remainder)>0) {
			$idParts = explode("/",$remainder);
			$id = $idParts[1];
		}
		parse_str($this->queryStr, $queryParams);
		switch($this->http_method) {
			case 'GET':
				if($id) {
					$content = $this->getLogMessage($id);
				} else {
					$content =  $this->getMessages($queryParams);
				}
			break;	
			case 'POST':
			case 'PUT':
				if($id) {
					throw new LoggingException("Unallowable method.");
				}
				//write to log
				$content = $this->postMessage($this->postedData);
			break;
			case 'DELETE':
				//delete from log
				if(!$id) {
					throw new LoggingException("ID is required for delete.");
				}
				$content = $this->deleteLogMessage($id);
			break;
		}
		if(!$content) return FALSE;
		\Utils::returnJSON($content);
  }
  
  public function deleteLogMessage($id) {
		$sql = "DELETE FROM `logging` WHERE `ID` = :id ";
		try {
			$sth = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
			$sth->execute(array(':id'=>$id));
		} catch (PDOException $e) {
        	throw new LoggingException($e->getMessage());
    	}
    	return array("Message deleted: " . $id);
  }
  
  public function getLogMessage($id) {
		$sql = "SELECT * FROM `logging` WHERE `ID` = :id ";
		try {
			$sth = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
			$sth->execute(array(':id'=>$id));
		} catch (PDOException $e) {
        	throw new LoggingException($e->getMessage());
    	}
		while($row = $sth->fetch( PDO::FETCH_ASSOC )){
          return $row;
		}
		$this->response = "No log found with given id.";
		$this->setError(404);
        return FALSE;
  }
  
  public function postMessage($doc) {
		$params = array();
		if(array_key_exists('LogLevel',$doc)) {
			$params[':LogLevel'] = $doc['LogLevel'];
		} else {
			$params[':LogLevel'] = 'INFO';
		}
		if(!array_key_exists('tag',$doc)) {
			throw new LoggingException("Log message MUST have a tag.");
		} else {
			$params[':tag'] = $doc['tag'];
		}
		if(!array_key_exists('mdml:sourceURI',$doc)) {
			throw new LoggingException("Log message MUST have an mdml:sourceURI.");
		} else {
			$params[':sourceURI'] = $doc['mdml:sourceURI'];
		}
		if(!array_key_exists('mdml:originURI',$doc)) {
			throw new LoggingException("Log message MUST have an mdml:originURI.");
		} else {
			$params[':originURI'] = $doc['mdml:originURI'];
		}
		if(!array_key_exists('Message',$doc)) {
			throw new LoggingException("Log message MUST have a Message.");
		} else {
			$params[':Message'] = $doc['Message'];
		}
		$sql = 'INSERT INTO `logging` (`tag`,`sourceURI`,`originURI`,`LogLevel`,`Message`) VALUES ';
		$sql .= '(:tag,:sourceURI,:originURI,:LogLevel,:Message)';
		try {
		$sth = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
	        $sth->execute($params);
		} catch (PDOException $e) {
        	throw new LoggingException($e->getMessage());
    	}
		$id = $this->db->lastInsertId();
		$url = $this->request->getURI()."/".$id;
		$doc['@id'] = $url;
		return $doc;
  }

  public function getMessages($queryParams) {
	    $params=array();
	    $paging=array();
	    $start = 0;
	    $count = $this->count;
	    if(array_key_exists('start',$queryParams)) {
			$paging['start'] = $queryParams['start'];
		}
		if(array_key_exists('count',$queryParams)) {
			$paging['count'] = $queryParams['count'];
		}
	    if(array_key_exists('tag',$queryParams)) {
			$params[':tag'] = $queryParams['tag'];
		}
		if(array_key_exists('from',$queryParams)) {
			$fromTS = strtotime($queryParams['from']);
			$params[':from'] = date('Y-m-d H:i:s', $fromTS);
		}
		if(array_key_exists('until',$queryParams)) {
			$untilTS = strtotime($queryParams['until']);
			$params[':until'] = date('Y-m-d H:i:s', $untilTS);
		}
		$sql = "SELECT * FROM `logging` WHERE 1=1 ";
		if(count($params)>0) {
			$clauses = array();
			foreach($params as $field=>$val) {
				switch($field) {
					case ':tag':
						$clauses[] = " `tag` LIKE :tag ";
					break;
					case ':from':
						$clauses[] = " `logged` >= :from ";
					break;
					case ':until':
						$clauses[] = " `logged` <= :until ";
					break;
				}
			}
			if(count($clauses)>0) {
					$sql .= " AND " . implode(" AND ",$clauses);
			}
		}
		if(array_key_exists('field',$this->filter) && array_key_exists('value',$this->filter)) {
			$fField = $this->filter['field'];
			$fValue = str_replace("*","%",$this->filter['value']);
			//handle the backslash problem
			if(strstr($fValue,"\\")) {
				$fValue = str_replace('\\','%',$fValue);
			}
			$sql .= " AND `".$fField."` LIKE '".$fValue."' ";
		}
		if(count($paging)>0) {
				$sql .= " LIMIT :start,:count ";
				$params[':start'] = $paging['start'];
				$params[':count'] = $paging['count'];
		}
		$sth = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$sth->execute($params);
		$msgs = array();
		while($row = $sth->fetch( PDO::FETCH_ASSOC )){
          $msgs[] = $row;
		}
		return $msgs;
  }
 

}

?>
