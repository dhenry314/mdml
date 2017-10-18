#!/usr/bin/php
<?php
set_time_limit(0);
require __DIR__ . '/../vendor/autoload.php';
if(!file_exists(__DIR__.'/../config.php')) {
        die("No config.php found in this directory.  \n
        Please copy config.example.php to config.php and change the settings as appropriate.\n
        Then run this script again.\n");
}
$config = include __DIR__ . '/../config.php';


use Elasticsearch\ClientBuilder;
$esURL = $config['elasticsearch']['host'].":".$config['elasticsearch']['port'];
$hosts = array($esURL);

$mappingContents = file_get_contents('../installFiles/mohub_dplaMapping.json');
$ESmapping = json_decode($mappingContents);
$JSONmsg = json_last_error_msg();
if(!strstr($JSONmsg,"No error")) {
	die("Could not load mapping file. ERRORS: " . $msg);
}

$indexName="mohub_dpla";
$client = ClientBuilder::create()->setHosts($hosts)->build();
$mappings = $client->indices()->getMapping();
if(array_key_exists($indexName,$mappings)) {
        $confirm = FALSE;
        $input = readline("Index " . $indexName . " already exists, do you want to delete it? (y|N): ");
        if(strlen($input)>0) {
                if(strtolower($input)=='y') {
                        $confirm = TRUE;
                } elseif(strtolower($input)=='n') {
                        $confirm = FALSE;
                } else {
                        die("unknown input: " . $input . "\n");
                }
        }
        if($confirm) {
                $params = array('index'=>$indexName);
                echo "Index " . $indexName . " being deleted.\n";
                $result = $client->indices()->delete($params);
                $contents = file_get_contents('./installFiles/resourceMapping.json');
                $params = array('index'=>$indexName,'body'=>$contents);
                echo "Creating index: " . $indexName . "\n";
                $result = $client->indices()->create($params);
        } else {
                echo("Keeping index " . $indexName . "\n");
        }
} else {
        $params = array('index'=>$indexName,'body'=>$mappingContents);
        echo "Creating index: " . $indexName . "\n";
        $result = $client->indices()->create($params);
}



//Mongodb
$connecting_string =  sprintf('mongodb://%s:%d/',
                              $config['mongo']['host'],
                              $config['mongo']['port']
                        );
if(array_key_exists('connectOptions',$config['mongo'])) {
	$options = $config['mongo']['connectOptions'];
} else {
	$options = array();
}
if(array_key_exists('adminPW', $config['mongo']) && array_key_exists('adminUser', $config['mongo'])) {
      $options['username'] = $config['mongo']['adminUser'];
      $options['password'] = $config['mongo']['adminPW'];
} else {
        die("No admin credentials for mongo in the configuration.\n\n");
}
$connection=  new \MongoDB\Client($connecting_string,$options);

try
{
    $mongoDB = $connection->selectDatabase($config['mongo']['database']);
    $coll = $mongoDB->selectCollection('mdml');
    #$cursor = $coll->find(array(),array('skip'=>546300));
    $cursor = $coll->find();
}
catch (\MongoDB\Driver\Exception\AuthenticationException $e) {
        die("Could not login to MongoDB. Please check Mongo adminUser and adminPW in config.php.\n
           ERROR: " . $e->getMessage() . "\n\n");
}
catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e)
{
        die("Could not list databases. Please check your Mongo configuration.\n
        ERROR: " . $e->getMessage(). "\n\n");
}
catch (\MongoDB\Driver\Exception\RuntimeException $e) {
        die("Could not list databases. Please check your Mongo configuration.\n
        ERROR: " . $e->getMessage() . "\n\n");
}

function getDPLARecord($payload) {
	global $ESmapping;
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
					break;
					case "array":
						if(is_string($val)) {
							$newVal = array($val);
						} elseif($givenType == 'boolean') {
							$newVal = array('');
						} elseif(is_object($val)) {
							$givenArr = (array)$val;
							$element = array_shift($givenArr);
							$newVal = array($element);
						}
					break;
				}
				if(!$newVal) {
					die("Wrong type for " . $field . ". Found " . getType($val) . " but should be " . $valType . "\n"
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

		} else {
			$dplaRecord->sourceResource->$field = $val;
		}
	}
	return $dplaRecord;
}


if(!$cursor) die("No results for query: " . print_r($query));

$results = array();
$n=0;
$indexed=0;
foreach($cursor as $hit) {
	echo $n."\n";
	$n++;
	$result = json_decode(json_encode( $hit->getArrayCopy() ));
        $resource = $result->{'mdml:resource'};
	if(!strstr($resource->mdml_endpoint,'/DPLA/')) {
			echo $resource->mdml_endpoint . "\n";
			continue;
	}
	if(!property_exists($result,'mdml:payload')) continue;
	if(!property_exists($result->{'mdml:payload'},'sourceResource')) continue;
	if(!property_exists($result->{'mdml:payload'}->sourceResource,'_id')) continue;
	$payload = $result->{'mdml:payload'};
	$sourceResource = $payload->sourceResource;
	$doc_id = $sourceResource->_id;
	//unset originalRecord
	unset($payload->sourceResource->originalRecord);
	$payload = getDPLARecord($payload);
	//DEBUG
	if(!property_exists($payload->sourceResource,'title')) {
		die("payload: " . print_r($result->{'mdml:payload'}) ."\n".
			" new payload: " . print_r($payload) . "\n==============\n"
		);
	}
	$payload->{'mdml:resource'} = $resource;
	$body = json_encode($payload);
	//index as necessary
	$params = array(
    		'index' => $indexName,
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
	#if($indexed>1000) break;
}

die("Indexed " . $indexed . " records.\n");
die("Done. \n\n");

?>
