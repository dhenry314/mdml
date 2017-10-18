<?php
//storage based on database and filesystem

class StorageConnectionException extends \Exception{};
class StorageQueryException extends \Exception{};
class StorageDeleteException extends \Exception{};
class StorageInsertException extends \Exception{};
class StorageUpdateException extends \Exception{};

class DbFsStorage implements iStorage {

  var $config;
  var $db;
  private $BASE62_ALPHABET = array(
        'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9');


  public function __construct() {
    $config = include __DIR__ . '/../config.php';
    $this->config = $config;
    try {
    	$this->db = new PDO($config['db']['connectStr'], $config['db']['user'], $config['db']['pw']);
    } catch (PDOException $e) {
    	throw new StorageConnectionException('Could not connect to database ' . $e->getMessage());
    }
  }


  public function getDocument($loc){
	$filePath = $this->locToFilepath($loc);
	$fullPath = $this->config['cacheDir'].$filePath;
	$contents = file_get_contents($fullPath);
	$doc = \Utils::jsonToObj($contents);
	return $doc;
  }

  public function removeDocument($loc){}

  public function saveDocument($doc,$loc){
	$filePath = $this->locToFilepath($loc);
	$toStore = \Utils::safe_json_encode($doc);
	return $this->writeToCache($filePath,$toStore);
  }

  private function locToFilepath($loc) {
	$pathParts = explode("/",$loc);
        $id = array_pop($pathParts);
        if(!ctype_digit($id)) {
                throw new StorageQueryException("Could not retrieve document with id: " . $id);
        }
        $path = implode("/",$pathParts);
        $code = $this->numToBase62($id);
        $codePath = $this->codeToPath($code);
        $filePath = $path."/".$codePath.$id.".json";
        if(strstr($filePath,'//')) {
                $filePath = str_replace('//','/',$filePath);
        }
	return $filePath;
  }

  private function writeToCache($path,$content) {
	$cachePath = $this->config['cacheDir'];
	if(substr($cachePath,-1,1) != "/") {
		$cachePath .= "/";
	}
	if(substr($path,0,1) == "/") {
		$path = substr($path,1);
	}
	$fullPath = $cachePath.$path;
	if(!file_exists($fullPath)) {
		if(!file_exists($cachePath)) {
			throw new StorageInsertException("CacheDir does not exist!");
		}
		$pathParts = explode("/",$path);
		$filename = array_pop($pathParts);
		$currentDir = $cachePath;
		foreach($pathParts as $part) {
			$currentDir .= $part."/";
			if(!is_dir($currentDir)) {
				mkdir($currentDir);
			}
		}
	}
	$fh = fopen($fullPath,"w");
	fwrite($fh,$content);
	fclose($fh);
	return $content;
  }

  private function codeToPath($code) {
	if(strlen($code)<3) {
	 $code = str_pad($code, 3, "0", STR_PAD_LEFT);
	}
	$c1 = substr($code,0,1);
	$c2 = substr($code,1,1);
	$c3 = substr($code,2,1);
	$path = $c1."/".$c2."/".$c3."/";
	return $path; 
  }

  public function upsert($doc,$loc){}

  public function insertDocument($doc,$loc){}

  public function updateDocument($doc,$loc){}

  public function getCount(){}

  private function numToBase62($id) {
        $alphabet = $this->BASE62_ALPHABET;
        $code = '';
        $codeDigits = array();
        $dividend = (int) $id;
        $remainder = 0;

        while ($dividend > 0) {
            $remainder = floor($dividend % 62);
            $dividend = floor($dividend / 62);
            array_unshift($codeDigits, $remainder);
        }

        foreach ($codeDigits as $v) {
            $code .= $alphabet[$v];
        }

        return $code;
    }

    private function base62ToNum($str) {
    
            $alphabet = $this->BASE62_ALPHABET;
            $len = strlen($str);
    	    $val = 0;
            $arr = array_flip($alphabet);
            for($i = 0; $i < $len; ++$i) {
               $val += $arr[$str[$i]] * pow(62, $len-$i-1);
            }
            return (int)$val;
   }



}

?>
