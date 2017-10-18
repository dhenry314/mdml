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

//usage
if(count($argv)<2) {
	die("USAGE: testOAI.php {OAIurl} {metadataPrefix} {set}(optional)\n\n");
}
$oaiPath = $argv[1];
$metadataPrefix=$argv[2];
$set = NULL;
if(array_key_exists(3,$argv)) {
	$set = $argv[3];
}

try {
     $oaiClient = new \Phpoaipmh\Client($oaiPath);
     $oaiEndpoint = new \Phpoaipmh\Endpoint($oaiClient);
} catch (\Exception $e) {
     throw new InvalidOAIingestor("Could not connect to OAI endpoint. ERROR: " . $e->getMessage());
}

try {
    $recs = $oaiEndpoint->listRecords($metadataPrefix,null,null,$set);
} catch (\Exception $e) {
    die("ERROR: " . $e->getMessage());
}
$n=1;
foreach($recs as $rec) {
   #$metadata = $rec->metadata->asXML();
   $header = $rec->header->asXML();
   echo $n. ") header: " . $header . "\n=================\n";
   $n++;
}

echo "Done.\n\n";
exit;

?>
