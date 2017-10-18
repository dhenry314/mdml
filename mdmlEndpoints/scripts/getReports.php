#!/usr/bin/php
<?php
set_time_limit(0);
require_once('common.inc');
//usage
if(count($argv)<2) {
	die("USAGE: getReports.php {NS}:{sourceID}:{endpointPattern} e.g. MOHUB:FRBSTL:/INCOMING/\n\n");
}

$token = getToken();

$NS = NULL;
$sourceID = NULL;
$endpointPattern = NULL;
if(array_key_exists(1,$argv)) {
	$processStr = $argv[1];
	$parts = explode(":",$processStr);
	$NS = array_shift($parts);
	if(count($parts)>0) {
		$sourceID = array_shift($parts);
	}
	if(count($parts)>0) {
		$endpointPattern = array_shift($parts);
	}
}
//load the processConfig
if(!file_exists('processConfig.json')) {
	die("No processConfig.json file found.\n");
}
$contents = file_get_contents('processConfig.json');
$processConfig = json_decode($contents,true);
$namespaces = array();
$reports = array();
$endpoints = array();
if($NS) {
	if(!array_key_exists($NS,$processConfig)) {
		die("Could not find NS: " . $NS . " in processConfig.\n\n");
	}
	$namespaces[] = $NS;
} else {
	$namespaces = array_keys($processConfig);
}

foreach($namespaces as $NS) {
	$reports[$NS] = array();
	//find logs
	if(!array_key_exists($NS,$processConfig)) die("Unknown namespace: " . $NS . "\n\n");
	$sourceIndexURL = $processConfig[$NS];
	$contents = file_get_contents($sourceIndexURL);
	$sourceIndex = json_decode($contents);
	foreach($sourceIndex->{'mdml:sources'} as $source) {
		if($sourceID) {
			if($source->{"@id"} != $sourceID) continue;
		}
		$reports[$NS][$source->{"@id"}] = array();
		foreach($source->{'mdml:sources'} as $mdml_source) {
			$sourceDefURL = $mdml_source->{"@id"};
			$sourceDefContents = file_get_contents($sourceDefURL);
			$sourceDef = json_decode($sourceDefContents);
			foreach($sourceDef->{"mdml:endpoints"} as $endpoint) {
					$fullEndpointPath = $endpoint->{"mdml:endpointNS"}.$endpoint->{"mdml:endpointPath"};
					if($endpointPattern) {
					    if(!strstr($fullEndpointPath,$endpointPattern)) continue;
					}
					$reports[$NS][$source->{"@id"}][$fullEndpointPath] = array();
					echo "Found matching endpoint at: " . $fullEndpointPath . "\n";
					$processIDs = getProcessIDs($fullEndpointPath,$token);
					if(count($processIDs)==0) {
						echo("There are no processIDs with this endpoint.\n\n");
						$reports[$NS][$source->{"@id"}][$fullEndpointPath]["logs"] = "No logs found.";
					} else {
					echo "Please select one of the following process IDs by number to show logs: \n";
					foreach($processIDs as $k=>$v) {
							$num = $k+1;
							echo $num.") ".$v . "\n";
					}
					$handle = fopen ("php://stdin","r");
					$line = fgets($handle);
					$num = (int) $line;
					$chosenProcessID = $processIDs[$num-1];
					$reports[$NS][$source->{"@id"}][$fullEndpointPath]["logs"] = getLogReport($chosenProcessID,$token);
					$reports[$NS][$source->{"@id"}][$fullEndpointPath]["info"] = getEndpointInfo($fullEndpointPath,$token);
					}
			}
  		}
	}
}

die("reports: " . print_r($reports) . "\n\n");


?>
