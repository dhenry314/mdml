<?php

namespace mdml;

class Utils {

  public static function getFileContents($url,$attempts=1) {
         if($attempts > 3) return FALSE;
         $content = @file_get_contents($url);
         if (strpos($http_response_header[0], "200")) {
                return $content;
         } elseif(strpos($http_response_header[0], "404")) {
                return FALSE;
         } else {
               echo "Error getting content from ".$url ."\n";
               echo "Trying again in 5 seconds.\n";
               sleep(5);
               Utils::getFileContents($url,$attempts+1);
         }
  }

  static function getCleanXML($xml_string) {
	$prefixes = array();
	//parse out prefixes
	$split1 = explode('xmlns:',$xml_string);
	//first part is the header so remove it
	array_shift($split1);
	foreach($split1 as $part) {
        	$split2 = explode('=',$part);
        	$prefixes[] = array_shift($split2);
	}

	//add default to prefixes
	$prefixes[] = 'default';

	foreach($prefixes as $prefix) {
        	//remove prefixed tags
        	$xml_string = str_replace($prefix.":","",$xml_string);
        	//remove namespace decalartions
        	$ns_declaration = NULL;
        	$split1 = explode("xmlns:".$prefix."=",$xml_string);
        	//first part is the header, so remove it
        	array_shift($split1);
        	foreach($split1 as $part) {
                	$quoteChr = substr($part,0,1);
                	$split2 = explode($quoteChr,$part);
                	//first element is empty
                	array_shift($split2);
                	$remainder = array_shift($split2);
                	//wrap in quoteChr
                	$remainder = $quoteChr.$remainder.$quoteChr;
                	$ns_declaration = 'xmlns:'.$prefix.'='.$remainder;
                	$xml_string = str_replace($ns_declaration,"",$xml_string);
        	}
	}
	
	$attrsToRemove = array('schemaLocation','xmlns','xsi');

	foreach($attrsToRemove as $attrPart) {
		//remove any schemaLocation attribute
		$split1 = explode($attrPart."=",$xml_string);
		//first part is the header - get rid of it
		array_shift($split1);
		foreach($split1 as $part) {
               		$quoteChr = substr($part,0,1);
               		$split2 = explode($quoteChr,$part);
               		//first element is empty
               		array_shift($split2);
               		$remainder = array_shift($split2);
               		//wrap in quoteChr
               		$remainder = $quoteChr.$remainder.$quoteChr;
               		$attr = $attrPart.'='.$remainder;
               		$xml_string = str_replace($attr,"",$xml_string);
        	}
	}

	return $xml_string;
  }


   static function returnXML($xml) {
	header("Content-Type: text/xml");
	echo $xml;
	exit;
   }

   static function returnJSON($obj) {
	$json = Utils::safe_json_encode($obj);
	header("Content-Type: application/json");
	echo $json;
	exit;
   }

   static function safe_json_encode($value){
    if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
        $encoded = json_encode($value, JSON_PRETTY_PRINT);
    } else {
        $encoded = json_encode($value);
    }
    switch (json_last_error()) {
        case JSON_ERROR_NONE:
            return $encoded;
        case JSON_ERROR_DEPTH:
            return "{'ERROR':'Maximum stack depth exceeded'}"; // or trigger_error() or throw new Exception()
        case JSON_ERROR_STATE_MISMATCH:
            return "{'ERROR':'Underflow or the modes mismatch'}"; // or trigger_error() or throw new Exception()
        case JSON_ERROR_CTRL_CHAR:
            return "{'ERROR':'Unexpected control character found'}";
        case JSON_ERROR_SYNTAX:
            return "{'ERROR','Syntax error, malformed JSON'}"; // or trigger_error() or throw new Exception()
        case JSON_ERROR_UTF8:
            $clean = Utils::utf8ize($value);
            return Utils::safe_json_encode($clean);
        default:
            return "{'ERROR':'Unknown error'}"; // or trigger_error() or throw new Exception()

    }
  }

  static function utf8ize($mixed) {
    $obj = FALSE;
    if (is_object($mixed)) {
        $mixed = (array)$mixed;
        $obj = TRUE;
    }
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = Utils::utf8ize($value);
        }
    } else if (is_string ($mixed)) {
        return utf8_encode($mixed);
    }
    if($obj) {
        $mixed = (object)$mixed;
    }
    return $mixed;
   }



   public static function utf8_for_xml($string) {
         $string = preg_replace('/[[:^print:]]/', '', $string);
         return preg_replace ('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
   }

   public static function hashFromContents($contents) {
        if(!is_string($contents)) {
                $contents = json_encode($contents);
        }
        return md5($contents);
  }

  public static function wrapAsJSON($data) {
      header('Content-type: application/json');
      return json_encode( $data );
  }

  public static function getPostedObj() {
      $rawData = file_get_contents("php://input");
      try {
        $postedObj = Utils::jsonToObj($rawData);
      } catch (\Exception $e) {
        throw new \InvalidArgumentException($e->getMessage());
      }
      return $postedObj;
  }

  static function arrayToObj($var) {
	if(is_object($var)) return $var;
	$obj = FALSE;
	if(is_array($var)) {
		$obj = new \stdClass();
		foreach($var as $k=>$v) {
			if(is_array($v)) {
				$obj->{$k} = Utils::arrayToObj($v);
			} else {
				$obj->{$k} = $v;
			}
		}
	}
	return $obj;
  }

  public static function jsonToObj($source) {
    $json_data = json_decode($source, true);
    if($json_data == null){
      throw new \Exception(json_last_error());
    }else{
      return $json_data;
    }
  }

  public static function urlExists ( $url ) {
    // Remove all illegal characters from a url
    $url = filter_var($url, FILTER_SANITIZE_URL);

    // Validate URI
    if (filter_var($url, FILTER_VALIDATE_URL) === FALSE
        // check only for http/https schemes.
        || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME)), ['http','https'], true )
    ) {
        return false;
    }

    // Check that URL exists
    $file_headers = @get_headers($url);
    return !(!$file_headers || $file_headers[0] === 'HTTP/1.1 404 Not Found');
  }

  public static function protectedURLExists($url,$jwt=NULL) {
	// create curl resource
	$ch = curl_init();

	// set url
	curl_setopt($ch, CURLOPT_URL, $url);
	if($jwt) {
		$authorization = "Authorization: Bearer ".$jwt;
        	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
	}
	//return the transfer as a string
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	$response = curl_exec($ch);
	
	$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$output = substr($response, 0, $header_size);

	// close curl resource to free up system resources
	curl_close($ch);

	$headers=array();

	$data=explode("\n",$output);
	$status = $data[0];
	$sParts = explode(" ",$status);
	$statusCode = $sParts[1];
	if(in_array($statusCode,array(200,301,301))) {
		return true;
	}
	return false;
  }

  public static function ObjFromJSONurl($url) {
	if(!$contents = Utils::getFileContents($url)) {
		throw new \Exception("Could not load contents from " . $url);
	}
	try {
		$obj = Utils::jsonToObj($contents);
	} catch(\Exception $e) {
		throw new \Exception("Could not parse json from ". $url);
	}
	return $obj;
  }

  public static function postToURL($url,$data,$jwt=NULL) {
	$headers = NULL;
	if($jwt) {
		$headers = "Authorization: Bearer ".$jwt;
	}
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $headers ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $json = Utils::safe_json_encode($data);
	curl_setopt($ch, CURLOPT_POSTFIELDS,$json);
	$response = curl_exec($ch);
        if(curl_errno($ch)){
              throw new \Exception("Curl error: "  . curl_error($ch));
        }
        curl_close($ch);
	$result = false;
	try {
        	$result = Utils::jsonToObj($response);
	} catch(\Exception $e) {
		throw new \Exception("Could not parse response from posted json. ERROR: " . $e->getMessage());
	}
	if(is_array($result)) {
		if(array_key_exists("exception",$result)) {
			throw new \Exception($result['exception'] . " " . $result['message']);
		}
	}
	return $result;
   }

   /**
  * Xml2Json - convert xml (DOM object) to a cooresponding json string
  * @param xml - a dom object from xml
  * @param attr_prefix - the prefix to use on any attribute
  * @param txtvar - the name that should be given to any text attribute
  */
  static function Xml2Json($xml,$attr_prefix=NULL,$txtvar=NULL) {
	$options = array();
	if($attr_prefix) {
			$options['attributePrefix'] = $attr_prefix;
	}
	if($txtvar) {
			$options['textContent'] = $txtvar;
	}
	$xmlArray = Utils::xmlToArray($xml,$options);
	return json_encode($xmlArray,JSON_PRETTY_PRINT);
  }

  static function xmlToArray($xml, $options = array()) {
    $defaults = array(
        'namespaceSeparator' => ':',//you may want this to be something other than a colon
        'attributePrefix' => 'mdml:',   //to distinguish between attributes and nodes with the same name
        'alwaysArray' => array(),   //array of xml tag names which should always become arrays
        'autoArray' => true,        //only create arrays for tags which appear more than once
        'textContent' => 'mdml:_TXT',       //key used for the text content of elements
        'autoText' => true,         //skip textContent key if node has no attributes or child nodes
        'keySearch' => false,       //optional search and replace on tag and attribute names
        'keyReplace' => false       //replace values for above search values (as passed to str_replace())
    );
    $options = array_merge($defaults, $options);
    $namespaces = $xml->getNamespaces(TRUE);
    //$defaultNS = NULL;
    //if(array_key_exists('',$namespaces)) {
    //	$defaultNS = $namespaces[''];
    //  }
    $namespaces[''] = null; //add base (empty) namespace

    //get attributes from all namespaces
    $attributesArray = array();
    foreach ($namespaces as $prefix => $namespace) {
        foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
            //replace characters in attribute name
            if ($options['keySearch']) $attributeName =
                    str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
            $attributeKey = $options['attributePrefix']
                    . ($prefix ? $prefix . $options['namespaceSeparator'] : '')
                    . $attributeName;
            $attributesArray[$attributeKey] = (string)$attribute;
        }
    }
    //add the default namespace
    //if(!array_key_exists('xmlns',$attributesArray)) {
    //	$attributesArray['xmlns'] = $defaultNS;
    //}

    //get child nodes from all namespaces
    $tagsArray = array();
	 foreach ($namespaces as $prefix => $namespace) {
        foreach ($xml->children($namespace) as $childXml) {
            //recurse into child nodes
            $childArray = Utils::xmlToArray($childXml, $options);
            list($childTagName, $childProperties) = each($childArray);

            //replace characters in tag name
            if ($options['keySearch']) $childTagName =
                    str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
            //add namespace prefix, if any
            if ($prefix) $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;

            if (!isset($tagsArray[$childTagName])) {
                //only entry with this key
                //test if tags of this type should always be arrays, no matter the element count
                $tagsArray[$childTagName] =
                        in_array($childTagName, $options['alwaysArray']) || !$options['autoArray']
                        ? array($childProperties) : $childProperties;
            } elseif (
                is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
                === range(0, count($tagsArray[$childTagName]) - 1)
            ) {
                //key already exists and is integer indexed array
                $tagsArray[$childTagName][] = $childProperties;
            } else {
                //key exists so convert to integer indexed array with previous value in position 0
                $tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
            }
        }
    }

    //get text content of node
    $textContentArray = array();
    $plainText = trim((string)$xml);
    $plainText = Utils::escapeJsonString($plainText);
    if ($plainText !== '') $textContentArray[$options['textContent']] = $plainText;

    //stick it all together
    $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
            ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;

    return array(
        $xml->getName() => $propertiesArray
    );
  }
  
  static function escapeJsonString($value) {
        # list from www.json.org: (\b backspace, \f formfeed)
		//make an exception for urls
		if(strstr($value,'http:') || strstr($value,'https:')) return $value;
                $escapers =     array("\\",     "/",   "\"",  "\n",  "\r",  "\t", "\x08", "\x0c");
                $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t",  "\\f",  "\\b");
                $result = str_replace($escapers, $replacements, $value);
                return $result;
  }
 
  static function JSONSearchReplacements($str,$reverse=FALSE) {
        $from = array('.@','?@','mdml:','mohub:','oai_dc:','dc:');
        $to = array('.__AT__','?__AT__','__MDML_','__MOHUB_','__OAIDC_','__DC_');
        if($reverse) {
                return str_replace($to,$from,$str);
        }
        return str_replace($from,$to,$str);
  }


  static function JSONSearch($expr,$data) {
        //do replacements
        $json = json_encode($data);
	switch($json) {
                case 'null';
                case '[null]';
                case 'NULL';
                case '[NULL]';
                        return FALSE;
                break;
        }
        $json = Utils::JSONSearchReplacements($json);
        $data = json_decode($json);
        $expr = Utils::JSONSearchReplacements($expr);
        //check expression for a filter
        if(strstr($expr,'[?')) {
                //check whether the path before the first filter points to an array
                $split1 = explode('[?',$expr);
                $subexpr = array_shift($split1);
                if(strlen($subexpr) > 0) {
                        $testdata = \JmesPath\Env::search($subexpr,$data);
                        if(!is_array($testdata)) {
                                $expr = '[?'.implode('[?',$split1);
                                //put the testdata object into an array
                                $data = array($testdata);
                                return Utils::JSONSearch($expr,$data);
                        }
                }
        }
        $result = NULL;
        //check for either string or array type for contains expression
        if(strstr($expr,'contains(')) {
                if(is_array($data)) {
                        if(is_object($data[0])) {
                                return FALSE;
                        }
                }
        }
        try{
                $result = \JmesPath\Env::search($expr, $data);
        } catch(Exception $e) {
                throw new Exception("ERROR: in JSONSearch: " . $e->getMessage() . " for expr: " . $expr . " in data: " . print_r($data));
        }
	//convert back from replacements
        $result_json = json_encode($result);
        $result_json = Utils::JSONSearchReplacements($result_json,TRUE);
        $result = json_decode($result_json);
        return $result;
  }

  static function tagToArray($tag) {
        $x = new \SimpleXMLElement($tag);
        $tagData =  (array)$x;
        return $tagData;
  }

  
  

}
