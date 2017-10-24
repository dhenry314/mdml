<?php

class ResourceSyncException extends \Exception{};

class ResourceSyncService {

  var $config;
  var $db;
  var $fromDaysAgo = "2";

  public function __construct() {
    $config = include __DIR__ . '/../config.php';
    $this->config = $config;
    try {
        $this->db = new PDO($config['db']['connectStr'], $config['db']['user'], $config['db']['pw']);
	$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        throw new StorageConnectionException('Could not connect to database ' . $e->getMessage());
    }

  }

  private function fullPath($path) {
	$path = $this->normalizePath($path);
	return $this->config['HTTP_PROTOCOL']."://".$_SERVER['SERVER_NAME'].$this->config['BASE_PATH'].$path."/";
  }

  private function getURL($row) {
	 $url = array();
         $url['loc'] = $this->fullPath($row['path']).$row['ID'];
	 $url['hash'] = "md5:".$row['hash'];
	 $url['lastMod'] = date('c',strtotime($row['lastmod']));
	 $url['mdml:sourceURI'] = $row['sourceURI'];
	 $url['mdml:originURI'] = $row['originURI'];
	 return $url;
  }

  private function isFileOrRecord($str) {
	if(ctype_digit($str)) return TRUE;
	if(in_array($str,array("resourcelist.xml","changelist.xml","info.json"))) return TRUE;
	return FALSE;
  }

  private function getBasePath($path) {
	$path = $this->normalizePath($path);
        //remove the ending file -- usually 'resourcelist.xml'
        $pathParts = explode("/",$path);
        if(count($pathParts)>1) {
                $filename = array_pop($pathParts);
                $basePath = implode("/",$pathParts);
		if($this->isFileOrRecord($filename)) {
			return $basePath;
		}
        }
	return $path;
  }

  public function getSitemap($format='xml') {
	//get a listing of distinct paths from the resources table
	$paths = array();
	$sql = "SELECT DISTINCT `path` FROM `resources`";
	foreach ($this->db->query($sql) as $row) {
		$paths[] = $this->fullPath($row['path'])."resourcelist.xml";
	}
	if($format == 'json') {
		 \Utils::returnJSON($paths);
	}
	return $this->createSitemapIndex($paths);
  }

  public function getInfo($path) {
	$info = array();
	$info['created'] = 0;
	$info['updated'] = 0;
	$info['deleted'] = 0;
	$path = $this->getBasePath($path);
	//get a listing of locs
        $params = array(':path'=>$path);
        $sql = "SELECT `change` FROM `resources` WHERE `path` = :path";
        $sth = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $sth->execute($params);
	$total = 0;
        while($row = $sth->fetch( PDO::FETCH_ASSOC )){
		$info[$row['change']]++;
		if($row['change'] != 'deleted') $total++;
        }
	$info['total'] = $total;
        return $info;
  }

  public function getPathResources($path,$paging,$filter=array()) {
	$path = $this->getBasePath($path);
	//get a listing of locs
        $params = array(':path'=>$path);
        $sql = "SELECT * FROM `resources` WHERE `path` = :path ";
	if(count($filter)>0) {
		if(array_key_exists('field',$filter) && array_key_exists('value',$filter)) {
			$fField = $filter['field'];
			$fValue = str_replace("*","%",$filter['value']);
			//handle the backslash problem
			if(strstr($fValue,"\\")) {
				$fValue = str_replace('\\','%',$fValue);
			}
			$sql .= " AND `".$fField."` LIKE '".$fValue."' ";
		}
	}
	$sql .= " AND NOT `change` = 'deleted'";
	$sql .= " LIMIT " . $paging['offset'].",".$paging['count'];
        $sth = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $sth->execute($params);
        $urls = array();
        while($row = $sth->fetch( PDO::FETCH_ASSOC )){
             $url = $this->getURL($row);
             $urls[] = $url;
        }
	return $urls;
  }

  public function getResourcelist($path,$format='xml',$paging,$filter=array()) {
	$path = $this->getBasePath($path);
  	//get a listing of urls
	$urls = $this->getPathResources($path,$paging,$filter);
	if($format == 'json') {
		\Utils::returnJSON($urls);
	}
	return $this->createURLSet($urls);
  }

  public function getChangelist($path,$queryParams) {
	$path = $this->normalizePath($path);
	$format = 'xml';
	if(array_key_exists('format',$queryParams)) {
		$format = $queryParams['format'];
	}
	//remove the ending file -- usually 'resourcelist.xml'
        $pathParts = explode("/",$path);
        array_pop($pathParts);
        $path = implode("/",$pathParts);
	$fromTS = strtotime("-".$this->fromDaysAgo." day");
	if(array_key_exists('from',$queryParams)) {
		$fromTS = strtotime($queryParams['from']);
	}
	$from = date('Y-m-d H:i:s', $fromTS);
	$untilTS = time();
	if(array_key_exists('until',$queryParams)) {
		$untilTS = strtotime($queryParams['until']);
	}
	$until = date('Y-m-d H:i:s', $untilTS);
	$params=array();
	$params[':path'] = $path;
	$params[':from'] = $from;
	$params[':until'] = $until;
	$sql = "SELECT * FROM `resources` WHERE `lastmod` >= :from AND `lastmod` <= :until AND path = :path";
	$sql .= " AND NOT `change` = 'deleted'";
	$sth = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $sth->execute($params);
        $urls = array();
        while($row = $sth->fetch( PDO::FETCH_ASSOC )){
             $url = $this->getURL($row);
             $urls[] = $url;
        }
	if($format == 'json') {
                \Utils::returnJSON($urls);
        }
        return $this->createURLSet($urls);
  }

  private function createURLSet($urls) {
	$contents = '<?xml version="1.0" encoding="UTF-8"?>
			<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        			xmlns:rs="http://www.openarchives.org/rs/terms/">';
        foreach($urls as $url) {
                $contents .= "<url>";
			$contents .= "<loc>".$url['loc']."</loc>";
			$contents .= "<lastMod>".$url['lastMod']."</lastMod>";
			$contents .= "<rs:md hash='".$url['hash']."'/>";
		$contents .= "</url>";
        }
        $contents .= "</urlset>";
        \Utils::returnXML($contents);
  }

  private function createSitemapIndex($sitemaps) {
	$contents = '<?xml version="1.0" encoding="UTF-8"?>
			<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
	foreach($sitemaps as $sitemap) {
		$contents .= "<sitemap><loc>".$sitemap."</loc></sitemap>";
	}
	$contents .= "</sitemapindex>";
	\Utils::returnXML($contents);
  }

  public function normalizePath($path) {
	$base_url = $this->config['HTTP_PROTOCOL']."://".$_SERVER['SERVER_NAME'].$this->config['BASE_PATH'];
	$path = str_replace($base_url,'',$path);
	//remove starting slash
	if(substr($path,0,1)=="/") {
		$path = substr($path,1);
	}
	//remove trailing slash
	if(substr($path,-1,1)=="/") {
		$path = substr($path,0,strlen($path)-1);
	}
	return $path;
  }

  public function existingResource($path,$sourceURI) {
	$params = array(':path'=>$path,':sourceURI'=>$sourceURI);
	$sql = "SELECT * FROM `resources` WHERE `path` = :path AND `sourceURI` = :sourceURI";
        $sth = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $sth->execute($params);
	if($result = $sth->fetch()) {
		return $result;
	}
	return FALSE;
  }

  public function getResource($loc) {
	$params = array();
        $pathParts = explode("/",$loc);
        $id = array_pop($pathParts);
        $path = implode("/",$pathParts);
        $params = array(':ID'=>$id,':path'=>$path);
        $sql = "SELECT * FROM `resources` WHERE `path` = :path AND `ID` = :ID AND NOT `change` = 'deleted'";
        $sth = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $sth->execute($params);
	$result = $sth->fetch();
	return $result;
  }

  public function saveResource($path,$originURI,$sourceURI,$hash=NULL,$change='created',$loc=NULL) {
	$path = $this->normalizePath($path);
	if($resource = $this->existingResource($path,$sourceURI)) {
		$id = $resource['ID'];
		$loc = $path."/".$id;
	} else {
		$pathParts = explode("/",$path);
		$lastPart = array_pop($pathParts);
		if(ctype_digit($lastPart)) {
			$id = $lastPart;
			$path = implode("/",$pathParts);
		}
	} 
	$params = array(':path'=>$path,':change'=>$change,':originURI'=>$originURI,':sourceURI'=>$sourceURI,':hash'=>$hash);
	if(!$id) {
		if($loc) {
			$id = trim(str_replace($path,'',$loc));
			$params[':ID'] = $id;
		}
	}
	if($id) {
		if($change != 'deleted') {
			$change = 'updated';
			$params[':change'] = $change;
		}
		$params[':ID'] = $id;
	}
	if($change != 'created') {
		$params[':lastMod'] = date("Y-m-d H:i:s");
	}
	switch($change) {
		case 'created':
			$sql = 'INSERT INTO `resources` (`change`,`path`,`originURI`,`sourceURI`,`hash`) VALUES ';
			$sql .= '(:change,:path,:originURI,:sourceURI,:hash)';
		break;
		case 'updated':
			if(!$id) {
				throw new ResourceSyncException("ID is required for update!");
			}
			if($resource['hash'] == $hash) {
				return FALSE;
			}
			$sql = 'UPDATE `resources` SET `change` = :change, `path` = :path, `originURI` = :originURI, ';
			$sql .= ' `sourceURI` = :sourceURI, `hash` = :hash, `lastMod` = :lastMod WHERE ID = :ID ';
		break;
		case 'deleted':
			 if(!$id) {
                                throw new ResourceSyncException("ID is required for delete!");
                        }
			unset($params[':hash']);
			$sql = 'UPDATE `resources` SET `change` = :change, `path` = :path, `originURI` = :originURI, ';
                        $sql .= ' `sourceURI` = :sourceURI, `lastMod` = :lastMod WHERE ID = :ID ';
		break;
		default:
			throw new ResourceSyncException("Unknown change value: " . $change);

	}
	try {
		$sth = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
	        $sth->execute($params);
	} catch (PDOException $e) {
        	throw new ResourceSyncException($e->getMessage());
    	}
	if(!$loc) {
		if(!$id) {
			$id = $this->db->lastInsertId();
		}
		if(substr($path,-1,1) != "/") {
			$path .= "/";
		}
		$loc = $path.$id;
	}
	return $loc;

  }

}

?>
