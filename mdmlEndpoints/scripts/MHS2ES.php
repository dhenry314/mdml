#!/usr/bin/php
<?php
set_time_limit(0);
require_once('common.inc');

require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';
use Elasticsearch\ClientBuilder;
$esURL = $config['elasticsearch']['host'].":".$config['elasticsearch']['port'];
$sourceIndexName = "ccsearchv2";
$sourceType = "record";
$targetIndex = "mohub_dpla";
$targetType = "dplaResource";
$hosts = array($esURL);

$client = ClientBuilder::create()->setHosts($hosts)->build();

$mappingContents = file_get_contents('../installFiles/mohub_dplaMapping.json');
$ESmapping = json_decode($mappingContents);
$JSONmsg = json_last_error_msg();
if(!strstr($JSONmsg,"No error")) {
        die("Could not load mapping file. ERRORS: " . $msg);
}


$serviceURL= "http://images.mohistory.org/services/getDPLAIds.php";
$offset = 0;
$indexed = 0;
while(\true) {
        $nextURL = $serviceURL."?offset=".$offset;
        if(!$contents = getFileContents($nextURL)) {
                break;
        }
        if($contents == 'false') break;
        $ids = jsonToObj($contents);
        foreach($ids as $id) {
               if(!$result = getRecord($id)) continue;
	       if(!$dplaRecord = getDPLARecord($result)) continue;
	       $doc_id = getID($dplaRecord->{"@id"});
               $resource = createResource($dplaRecord);
               $dplaRecord->{'mdml:resource'} = $resource;
               $body = json_encode($dplaRecord);
               //index as necessary
        	$params = array(
                	'index' => $targetIndex,
                	'type' => 'dplaResource',
                	'id' => $doc_id,
                	'body' => $body
        	);
	        try {
        	        $response = $client->index($params);
        	} catch(\Elasticsearch\Common\Exceptions\BadRequest400Exception $e) {
                	echo "Skipping record because it could not be indexed. ERRORS: " . $e->getMessage() ."\n record: " . $body . "\n";
        	}
        	$indexed++;
        	echo "indexed " . $indexed . "\n";
        }
        $offset += 100;
}

function createResource($dplaRecord) {
	$jsonRec = json_encode($dplaRecord);
	$hash = md5($jsonRec);
	$rParts = explode("/",$dplaRecord->{"@id"});
	$resourceID = array_pop($rParts);
	$resource = array();
	$resource['mdml_endpoint'] = "MOHUB/MHM/ALL/DPLA/";
        $resource['rs_md_change'] = "created";
        $resource['rs_md_datetime'] = date("c");
        $resource['rs_md_hash'] = $hash;
        $resource['sm_lastmod'] = $resource['rs_md_datetime'];
        $resource['sm_loc'] = "http://data.mohistory.org/mdml/ENDPOINTS/MOHUB/MHM/ALL/DPLA/ID:http_collections.mohistory.org/resource/".$resourceID;
	return $resource;	
}

function getMimsyGenre($params=NULL) {
        switch($params) {
                case 'O':
                        return 'Physical Object';
                break;
                case 'M':
                case 'P':
                        return 'Photograph/Pictorial Works';
                break;
                case 'A':
                case 'L':
                        return 'Book';
                break;
        }
        return $params;
}


function getRightsStatement($params=NULL) {
        switch($params) {
                case 'UND':
                        return 'http://rightsstatements.org/vocab/UND/1.0/';
                break;
                case 'NKC':
                        return 'http://rightsstatements.org/vocab/UND/1.0/';
                break;
                case 'CNE':
                        return 'http://rightsstatements.org/vocab/CNE/1.0/';
                break;
                case 'NoC-US':
                        return 'http://rightsstatements.org/vocab/NoC-US/1.0/';
                break;
                case 'NoC-OKLR':
                        return 'http://rightsstatements.org/vocab/NoC-OKLR/1.0/';
                break;
                case 'NoC-NC':
                        return 'http://rightsstatements.org/vocab/NoC-NC/1.0/';
                break;
                case 'NoC-CR':
                        return 'http://rightsstatements.org/vocab/NoC-CR/1.0/';
                break;
                case 'InC-RUU':
                        return 'http://rightsstatements.org/vocab/InC-RUU/1.0/';
                break;
                case 'InC-NC':
                        return 'http://rightsstatements.org/vocab/InC-NC/1.0/';
                break;
                case 'InC-EDU':
                        return 'http://rightsstatements.org/vocab/InC-EDU/1.0/';
                break;
                case 'InC':
                        return 'http://rightsstatements.org/vocab/InC/1.0/';
                break;
        }
        return $params;
}

function getID($itemID) {
	$parts = explode("/",$itemID);
	$resource = array_pop($parts);
	return "urn:MOHUB_MHM_ALL_DPLA:resource:".$resource;
}


function getDPLARecord($result) {
        global $ESmapping;
	if(!array_key_exists('_source',$result)) return FALSE;
	$mRec = $result['_source'];
	$payload = new \stdclass();
	$payload->{"@context"} = "http://dp.la/api/items/context";
        $payload->dataProvider = "Missouri Historical Society";
        $payload->hasView = new \stdclass();
	$payload->hasView->{"@id"} = $mRec['itemID'];
        $payload->{"@id"} = $mRec['itemID'];
        $payload->object = new \stdclass();
	$payload->object->{"@id"} = $mRec['assets'][0]['url']."thumb.jpg";
        $payload->aggregatedCHO = "#sourceResource";
        $payload->{"@type"} = "ore:Aggregation";
        $payload->ingestDate = date("c");
	$payload->isShownAt = $payload->hasView;
        $payload->provider = new \stdclass();
	$payload->provider->{"@id"} = "http://dp.la/api/contributor/missouri-hub";
	$payload->provider->name =  "Missouri Hub";
	$sourceResource = new \stdclass();
	$sourceResource->creator = $mRec['makers'];
	$sourceResource->description = $mRec['description'];
	$sourceResource->language = "eng";
	$sourceResource->rights = getRightsStatement($mRec['rights']);
	if(is_array($mRec['types'])) {
		$typeArr = array_shift($mRec['types']);
		$sourceResource->typeOfResource = $typeArr['label'];
	}
	$sourceResource->title = $mRec['label'];
	$sourceResource->date = $mRec['date1'];
	$sourceResource->dateCreated = $sourceResource->date;
	if(array_key_exists('collectionLabel',$mRec)) {
		$sourceResource->relation = $mRec['collectionLabel'];
	}
	$sourceResource->stateLocatedIn = array();
	$state = new \stdclass();
	$state->name =  "Missouri";
	$sourceResource->stateLocatedIn[] = $state;
	$sourceResource->identifier = $mRec['ids'][0];
	$sourceResource->type = $sourceResource->typeOfResource;
	$subject = array();
	if(is_array($mRec['subjects'])) {
		foreach($mRec['subjects'] as $mSubject) {
			$subject[] = $mSubject['label'];
		}
	}
	$sourceResource->subject = $subject;
	$sourceResource->genre = getMimsyGenre($mRec['mimsyRecordType']);
	$sourceResource->ingestType = "item";
	$payload->sourceResource = $sourceResource;
        
	$dplaRecord = new \stdclass();
        $properties = $ESmapping->mappings->dplaResource->properties;
        $sourceResourceProperties = $properties->sourceResource->properties;
        $sourceResource = (array)$payload->sourceResource;
        $payloadData = (array)$payload;
        $idFields = array("hasView","isShownAt","object","provider");
        foreach($payloadData as $field=>$val) {
                $newVal = NULL;
                if(!$val) $newVal = 'null';
                if(in_array($field,$idFields)) {
                        if(is_string($val)) {
                                $newVal = new \stdclass();
                                $newVal->{'@id'} = $val;
                        } elseif(is_array($val)) {
                                $newVal = new \stdclass();
                                if(strlen($val[0]>0)) {
                                        $newVal->{'@id'} = $val[0];
                                }
                        } elseif(is_object($val)) {
                                $newVal = $val;
                        }
                } elseif(is_string($val)) {
                        $newVal = $val;
                } elseif(!$val) {
                        $newVal = 'null';
                }
                $dplaRecord->$field = $newVal;
        }
        $dplaRecord->sourceResource = new \stdclass();
	 foreach($sourceResource as $field=>$val) {
                if(property_exists($sourceResourceProperties,$field)) {
                        $newVal = NULL;
                        if(!$val) {
                                if($field == 'language') {
                                  $newVal = 'eng';
                                } else {
                                  $newVal = '';
                                }
                        }
                        if(!property_exists($sourceResourceProperties->$field,'type')) continue;
                        $valType = $sourceResourceProperties->$field->type;
                        $givenType = getType($val);
                        if($valType != $givenType) {
                                switch($valType) {
                                        case "string":
                                                if(is_array($val)) {
                                                        $newVal = $val[0];
                                                        if(!$val) $newVal = 'null';
                                                } elseif(!$val) {
                                                        $newVal = 'null';
                                                } elseif(is_object($val)) {
                                                        $givenArr = (array)$val;
                                                        $newVal = array_shift($givenArr);
                                                }
						if(!$newVal) {
							$newVal = '';
						}
                                        break;
                                        case "array":
						if(is_string($val)) {
							$newVal = array();
							$newVal[] = $val;
						} elseif(!$val) {
							$newVal = array();
                                                } elseif($givenType == 'boolean') {
                                                        $newVal = array('');
                                                } elseif(is_object($val)) {
                                                        $givenArr = (array)$val;
                                                        $element = array_shift($givenArr);
                                                        $newVal = array($element);
                                                }
						if(!$newVal) {
							$newVal = array();
						}
                                        break;
                                }
				if(is_null($newVal)) {
                                        die("NewVal: " . $newVal . " Wrong type for " . $field . ". Found " . getType($val) . " but should be " . $valType . "\n"
                                        . print_r($payload) . "\n");
                                }
                                $dplaRecord->sourceResource->$field = $newVal;
                        } else {
				$dplaRecord->sourceResource->$field = $val;
			}
                         //handle rightsstatements
                        if($field == 'rights') {
                                if(is_array($val)) {
                                        $val = array_shift($val);
                                }
                                if(is_object($val)) {
                                        if(property_exists($val,'a')) {
                                                if(property_exists($val->a,'mdml:href')) {
                                                        $dplaRecord->sourceResource->$field = $val->a->{'mdml:href'};
                                                }
                                        }
                                }
                        }

                }
        }
        return $dplaRecord;
}



function getRecord($id) {
		global $client,$sourceIndexName,$sourceType;
               $query = '{
                        "query" : {
                                "match_phrase": {"ids": "'.$id.'"}
                        }
                }';

                $params = array(
                        'index' => $sourceIndexName,
                        'type' => $sourceType,
                        'body' => $query
                );
                try {
                        $response = $client->search($params);
                } catch (\Exception $e) {
                        die($e->getMessage());
                }
                if(count($response['hits']['hits'])>0) {
                        return $response['hits']['hits'][0];
                }
                return FALSE;
} 

?>
