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
	if($path == 'info.json') {
		return $this->getAllEndpointsInfo();
	}
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

  public function getAllEndpointsInfo() {
  	$data = array();
	$sql = "SELECT COUNT(*) AS `Rows`, `path` FROM `resources` GROUP BY `path` ORDER BY `path`";
	$sth = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
	$sth->execute($params);
	$total = 0;
	while($row = $sth->fetch( PDO::FETCH_ASSOC )){
	      $data[$row['path']] = $this->getInfo($row['path']);
	}
	return $data;
  }

  public function getPathResources($path,$paging,$filter=array()) {
	$path = $this->getBasePath($path);
	//set paging defaults
	if(!array_key_exists('offset',$paging)) {
		$paging['offset'] = 0;
	}
	if(!array_key_exists('count',$paging)) {
		$paging['count'] = 20;
	}
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
		} elseif(array_key_exists('where_clause',$filter)) {
			$sql .= " AND ".$filter['where_clause'];
		}
	}
	$sql .= " AND NOT `change` = 'deleted'";
	$sql .= " ORDER BY `lastmod` desc ";
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

  public function getResourcelist($path,$format='xml',$queryParams=array(),$filter=array()) {
	    $path = $this->getBasePath($path);
	    $paging = array();
	    if(array_key_exists('offset',$queryParams)) {
	       $paging['offset'] = $queryParams['offset'];
	    }
	    if(array_key_exists('count',$queryParams)) {
	       $paging['count'] = $queryParams['count'];
	    }

      if(array_key_exists('field',$queryParams)) {
          $filter['field']  = $queryParams['field'];
          if(array_key_exists('value', $queryParams)) {
            $filter['value'] = $queryParams['value'];
          }
      }
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
	$filter=array();
	$filter['where_clause'] = "`lastmod` >= '".$from."' AND `lastmod` <= '".$until."'";
	$paging = array();
	if(array_key_exists('offset',$queryParams)) {
	        $paging['offset'] = $queryParams['offset'];
	}
	if(array_key_exists('count',$queryParams)) {
	        $paging['count'] = $queryParams['count'];
	}

	$urls = $this->getPathResources($path,$paging,$filter);
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
	$pathParts = explode("/",$path);
	$lastPart = array_pop($pathParts);
	if(ctype_digit($lastPart)) {
	       $path = implode("/",$pathParts);
	}
	return $path;
  }

  public function existingResource($path,$sourceURI) {
	$params = array(':sourceURI'=>$sourceURI);
	$sql = "SELECT * FROM `resources` WHERE `sourceURI` = :sourceURI";
        $sth = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $sth->execute($params);
	while($result = $sth->fetch()) {
		if($result['path'] == $path) {
		   return $result;
		}
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
	if(!$hash) {
		throw new ResourceSyncException(
			"Cannot save resource. Missing hash."
		);
	}
	$loc = NULL;
	$params = array(
		':path'=>$path,
		':change'=>$change,
		':sourceURI'=>$sourceURI,
		':hash'=>$hash
	);
	if($resource = $this->existingResource($path,$sourceURI)) {
		if($change != 'deleted') {
			$change = 'updated';
			$params[':change'] = $change;
		}
		$loc = $path."/".$resource['ID'];
	}
	if($change != 'created') {
		$params[':lastMod'] = date("Y-m-d H:i:s");
	}
	switch($change) {
		case 'created':
			$params[':originURI'] = $originURI;
			$sql = 'INSERT INTO `resources` (`change`,`path`,`originURI`,`sourceURI`,`hash`) VALUES ';
			$sql .= '(:change,:path,:originURI,:sourceURI,:hash)';
		break;
		case 'updated':
			if($resource['hash'] == $hash) {
				return FALSE;
			}
			$sql = 'UPDATE `resources` SET `change` = :change, `hash` = :hash, ';
			$sql .= '`lastMod` = :lastMod WHERE `path` = :path AND `sourceURI` = :sourceURI ';
		break;
		case 'deleted':
			$sql = 'UPDATE `resources` SET `change` = :change, `hash` = :hash, ';
			$sql .= '`lastMod` = :lastMod WHERE `path` = :path AND `sourceURI` = :sourceURI ';
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
		$id = $this->db->lastInsertId();
		if(substr($path,-1,1) != "/") {
			$path .= "/";
		}
		$loc = $path.$id;
	}
	return $loc;
  }

}

?>
