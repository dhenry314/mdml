#!/usr/bin/php
<?php
require __DIR__ . '/vendor/autoload.php';
if(!file_exists(__DIR__.'/config.php')) {
	die("No config.php found in this directory.  \n
	Please copy config.example.php to config.php and change the settings as appropriate.\n
	Then run this script again.\n");
}
$config = include __DIR__ . '/config.php';
//db setup
try {
    $conn = new PDO($config['db']['connectStr'], $config['db']['user'], $config['db']['pw']);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
	die("Could not connect with given db params. ERROR: " . $e->getMessage());
}

try {
    $sql = file_get_contents("./installFiles/db_mapping.sql");
    // use exec() because no results are returned
    $conn->exec($sql);
    }
catch(PDOException $e)
    {
    echo $sql . "<br>" . $e->getMessage();
}

$conn = null;
if(!array_key_exists('storageClass',$config)) {
	die("No storageClass defined in the config.\n");
}
if($config['storageClass'] == 'DbFsStorage') {
	//check the cacheDir
	if (!is_writable($config['cacheDir'])) {
		die("cacheDir: " . $config['cacheDir'] ." is NOT writable!\n");
	}
	
} elseif($config['storageClass'] == 'MongoStorage') {
	echo "Checking MongoDB setup.\n\n";
	if(!array_key_exists('mongo',$config)) {
		die("No configuration for MongoDB found. \n
		Please create a mongo confuration as found in config.example.php\n\n");
	}
	if(array_key_exists('connect_string',$config['mongo'])) {
		$connecting_string = $config['mongo']['connect_string'];
	} else {
		$connecting_string =  sprintf('mongodb://%s:%d/',
                              $config['mongo']['host'],
                              $config['mongo']['port']
                        );
	}
	$options = array();
	if(array_key_exists('adminPW', $config['mongo']) && array_key_exists('adminUser', $config['mongo'])) {
      		$options['username'] = $config['mongo']['adminUser'];
      		$options['password'] = $config['mongo']['adminPW'];
	} else {
		die("No admin credentials for mongo in the configuration.\n\n");
	}
	$connection=  new \MongoDB\Client($connecting_string,$options);
	try 
	{
    		$dbs = $connection->listDatabases();
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
	$dbFound=FALSE;
	foreach($dbs as $db) {
		$dbName = $db->getName();
		if($dbName == $config['mongo']['database']) {
			$dbFound=TRUE;
			break;
		}
	}
	if(!$dbFound) {
		die("The mongo db from the config [".$config['mongo']['database']."] was not found!\n\n");
	}

	try {
		$mongoDB = $connection->selectDatabase($dbName);
		$colls = $mongoDB->listCollections();
	} catch(\MongoDB\Driver\Exception\AuthenticationException $e) {
		die("Could not list collections with the given username and password.\n 
			Please check your mongo configuration for user and pw.\n
			ERROR: " . $e->getMessage() . "\n\n"
		);
	}
	$foundColls = array();
	foreach($colls as $coll) {
		$collName = $coll->getName();
		if(in_array($collName,$config['mongo']['collections'])) {
			$foundColls[] = $collName;
		}
	}
	foreach($foundColls as $foundColl) {
		$input = readline("Collection " . $foundColl . " already exists, do you want to drop it? (y|N): ");
        	if(strlen($input)>0) {
			if(strtolower($input)=='y') {
				echo "Dropping collection " . $foundColl . "\n";
				try {
					$result = $mongoDB->dropCollection($foundColl);
				} catch (Exception $e) {
					die("Something went wrong dropping the collection " . $foundColl . " 
						ERROR: " . $e->getMessage() . "\n\n");
				}
				if($result->ok != 1) {
					die("Something went wrong dropping the collection " . $foundColl . " 
						ERROR: " . $result->errmsg . "\n\n");
				}
				//recreate the collection
				echo "Recreating collection " . $foundColl . "\n";
				try {
					$result = $mongoDB->createCollection($foundColl);
				} catch (Exception $e) {
					die("Something went wrong creating the collection " . $foundColl . " 
						ERROR: " . $e->getMessage() . "\n\n");
				}
				if($result->ok != 1) {
                                	die("Something went wrong creating the collection " . $foundColl . " 
						ERROR: " . $result->errmsg . "\n\n");
                        	}
			
			} else {
				echo ("Keeping collection " . $foundColl . "\n");
			}
		}	
	}

	foreach($config['mongo']['collections'] as $coll) {
		if(in_array($coll,$foundColls)) continue;
	 	echo "Creating collection " . $coll . "\n";
	 	try {
               		$result = $mongoDB->createCollection($coll);
         	} catch (Exception $e) {
               		die("Something went wrong creating the collection " . $coll . " ERROR: " . $e->getMessage() . "\n\n");
         	}
         	if($result->ok != 1) {
               		die("Something went wrong creating the collection " . $coll . " ERROR: " . $result->errmsg . "\n\n");
         	}
	}

	echo "Creating Mongo indices.\n\n";
	foreach($config['mongo']['indices'] as $collName=>$indices) {
		$coll = $mongoDB->selectCollection($collName);
		foreach($indices as $field=>$value) {
			$coll->createIndex(array($field=>$value));
		}
	}
}
echo "Done.\n\n";

?>

