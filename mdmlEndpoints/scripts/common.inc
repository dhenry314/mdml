<?php

function getProcessIDs($endpoint,$token) {
  $data_string = '{
                "mdml:logQuery": {
                        "mdml:endpoint": "'.$endpoint.'"
                },
                "mdml:distinctField": "mdml:processID" 
         }';
  $headers = array(
        'Content-Type: application/json',
        sprintf('Authorization: Bearer %s', $token),
        'Content-Length: ' . strlen($data_string)
  );

  $curl = curl_init("http://data.mohistory.org/mdml/SERVICES/mdml/LogReaderService");

  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
  $result = json_decode(curl_exec($curl));

  return $result;
}

function getLogs($sourceIndexURL,$token,$sourceID=NULL,$endpointPattern=NULL) {
	$logs = array();
	$contents = file_get_contents($sourceIndexURL);
	$sourceIndex = json_decode($contents);
	foreach($sourceIndex->{'mdml:sources'} as $source) {
			if($sourceID) {
					if($source->{"@id"} != $sourceID) continue;
			}
			foreach($source->{'mdml:sources'} as $mdml_source) {
					$sourceDefURL = $mdml_source->{"@id"};
					$sourceDefContents = file_get_contents($sourceDefURL);
					$sourceDef = json_decode($sourceDefContents);
					foreach($sourceDef->{"mdml:endpoints"} as $endpoint) {
							$fullEndpointPath = $endpoint->{"mdml:endpointNS"}.$endpoint->{"mdml:endpointPath"};
							if($endpointPattern) {
							    if(!strstr($fullEndpointPath,$endpointPattern)) continue;
							}
							echo "Found matching endpoint at: " . $fullEndpointPath . "\n";
							$endpoints[] = $fullEndpointPath;
							$processIDs = getProcessIDs($fullEndpointPath,$token);
							if(count($processIDs)==0) {
									die("There are no processIDs in logs matching your arguments.\n\n");
							}
							echo "Please select one of the following process IDs by number: \n";
							foreach($processIDs as $k=>$v) {
									$num = $k+1;
									echo $num.") ".$v . "\n";
							}
							$handle = fopen ("php://stdin","r");
							$line = fgets($handle);
							$num = (int) $line;
							$chosenProcessID = $processIDs[$num-1];
							$logs[$chosenProcessID] = getLogReport($chosenProcessID,$token);
					}
			}
	}
	return $logs;
}


function removeLogsByProcessID($processID,$token) {
   $data_string = '{
   	"mdml:logQuery": {
       		"mdml:processID" : "'.$processID.'"
   	},
   	"mdml:purgeByQuery": true
    }';
    $headers = array(
        'Content-Type: application/json',
        sprintf('Authorization: Bearer %s', $token),
        'Content-Length: ' . strlen($data_string)
  );

  $curl = curl_init("http://data.mohistory.org/mdml/SERVICES/mdml/LogReaderService");

  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
  $result = json_decode(curl_exec($curl));

  return $result;
}

function getToken() {
        $contents = file_get_contents("http://data.mohistory.org/mdml/CORE/login?username=mdmlAdmin&password=Tw0htbc0");
        $result = json_decode($contents);
        return $result->JWT;
}

function validateProcess($process,$token) {
  $data_string = json_encode($process);
  $headers = array(
        'Content-Type: application/json',
        sprintf('Authorization: Bearer %s', $token),
        'Content-Length: ' . strlen($data_string)
  );

  $curl = curl_init("http://data.mohistory.org/mdml/SERVICES/mdml/ValidationService");

  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
  $result = json_decode(curl_exec($curl));

  return $result;

}

function getAPIResult($url,$body,$token) {
  $data_string = json_encode($body);
  $headers = array(
        'Content-Type: application/json',
        sprintf('Authorization: Bearer %s', $token),
        'Content-Length: ' . strlen($data_string)
  );

  echo "Calling service at: " . $url . "\n";
  echo "with data_string: \n" . $data_string . "\n";
  $curl = curl_init($url);

  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
  $raw_content = curl_exec($curl);

  $error_msg = NULL;
  if ($raw_content === FALSE) {
    // some kind of an error happened
    $error_msg = curl_error($curl);
  }
  curl_close($curl); // close cURL handler
  if($error_msg) {
	return $error_msg;
  }
  $result = json_decode($raw_content);

  return $result;
}

function queryLogs($data_string,$token) {
  $headers = array(
        'Content-Type: application/json',
        sprintf('Authorization: Bearer %s', $token),
        'Content-Length: ' . strlen($data_string)
  );

  $curl = curl_init("http://data.mohistory.org/mdml/SERVICES/mdml/LogReaderService");

  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
  $result = json_decode(curl_exec($curl));

  return $result;
}

function getLogReport($processID,$token) {
	$report = array();
	$query = '{
		"mdml:logQuery": {
			"mdml:processID": "'.$processID.'"
     		},
     		"mdml:distinctField": "mdml:logLevel"
	}';
	$logLevels = queryLogs($query,$token);
	foreach($logLevels as $logLevel) {
		$query = '{
			"mdml:logQuery": {
				"mdml:processID": "'.$processID.'",
				"mdml:logLevel": "'.$logLevel.'"
			},
			"mdml:countOnly": true
		}';
		$result = queryLogs($query,$token); 
		$report[$logLevel] = $result->count;
	}
	return $report;
}

function getEndpointInfo($endpoint,$token) {
	$endpointBase = "http://data.mohistory.org/mdml/ENDPOINTS/";
	$url = $endpointBase.$endpoint."info";
	$headers = array(
        	'Content-Type: application/json',
        	sprintf('Authorization: Bearer %s', $token)
	);
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  	$result = json_decode(curl_exec($curl));
        return $result;	
}

function jsonToObj($source) {
    $json_data = json_decode($source, true);
    if($json_data == null){
      throw new Exception(json_last_error());
    }else{
      return $json_data;
    }
  }


function getFileContents($url,$attempts=1) {
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
               return getFileContents($url,$attempts+1);
         }
}


?>
