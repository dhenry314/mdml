#!/usr/bin/php
<?php
set_time_limit(0);
require_once('common.inc');
//usage
if(count($argv)<2) {
	die("USAGE: runEndpoint.php {NS}:{sourceID}:{endpointPattern} {validateFlag (optional)} e.g. MOHUB:FRBSTL:/INCOMING/\n\n");
}
$processStr = $argv[1];
$validate=FALSE;
if(array_key_exists(2,$argv)) {
	if(in_array(strtolower($argv[2]),array('y','yes','true','1','validate'))) {
		$validate = TRUE;
	}
}
$parts = explode(":",$processStr);
$NS = $parts[0];
$sourceID = $parts[1];
$endpointPattern = $parts[2];
//create a processID to use
$processID = $NS."_".$sourceID."_".date("YmdHis");
//load the processConfig
if(!file_exists('processConfig.json')) {
	die("No processConfig.json file found.\n");
}
$contents = file_get_contents('processConfig.json');
$processConfig = json_decode($contents);
if(!property_exists($processConfig,$NS)) {
	die("Could not find NS: " . $NS . " in processConfig.\n\n");
}

$token = getToken();

$sourceIndexURL = $processConfig->{$NS};
$contents = file_get_contents($sourceIndexURL);
$sourceIndex = json_decode($contents);
foreach($sourceIndex->{'mdml:sources'} as $source) {
	if($source->{"@id"} == $sourceID) {
		echo "Trying " . $sourceID . "\n";
		foreach($source->{'mdml:sources'} as $mdml_source) {
			$sourceDefURL = $mdml_source->{"@id"};
			echo "sourceDefURL: " . $sourceDefURL . "\n";
			$sourceDefContents = file_get_contents($sourceDefURL);
			if(!$sourceDef = json_decode($sourceDefContents)) {
				die("Could not load json.");
      				die(json_last_error());
    			}
			if(!property_exists($sourceDef,'mdml:endpoints')) {
				die("No endpoints defined in source def.\n\n");
			}
			foreach($sourceDef->{"mdml:endpoints"} as $endpoint) {
				$fullEndpointPath = $endpoint->{"mdml:endpointNS"}.$endpoint->{"mdml:endpointPath"};
				if(strstr($fullEndpointPath,$endpointPattern)) {
					echo "Matched endpoint " . $fullEndpointPath . " with pattern: " . $endpointPattern . "\n";
					foreach($endpoint->{"mdml:processes"} as $process) {
						if(!property_exists($process,"mdml:processID")) {
							$process->{"mdml:processID"} = $processID;
						}
						if(!property_exists($process,"mdml:endpoint")) {
							$process->{"mdml:endpoint"} = $fullEndpointPath;
						}
						if($validate) {
							echo "Validating " . $fullEndpointPath . "\n";
							$result = validateProcess($process,$token);
							echo "validation result: " . print_r($result) ."\n";
						} else {
							echo "Processing " . $fullEndpointPath . "\n";
							$result = getAPIResult($process->{"mdml:ServicePath"},$process,$token);
							die("Process result: " . print_r($result) . "\n\n");
						}
					}
				}
			}
		}
	}
}
if(!$validate) {
	die("ProcessID: " . $processID . "\n\n");
}
echo "Done.\n\n";
exit;

?>
