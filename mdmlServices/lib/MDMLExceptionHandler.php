<?php

namespace mdml;

class MDMLExceptionHandler {
  
   private $debug = FALSE; /* will show full trace when set to TRUE */

   public function __invoke($request, $response, $e) {
     // create a JSON-WSP Fault wrapper
     $fault = new \stdclass;
     $fault->type = "jsonwsp/fault";
     $fault->version = "1.0";
     $fault->fault = new \stdclass;
     $fault->fault->code = "server";
     
     // Add the exception class name, message and stack trace to response
      $data['exception'] = get_class($e); // Reflection might be better here
      if(strstr($data['exception'],'ServiceRoutingException') || strstr($data['exception'],'InvalidJSONException')) {
	$fault->fault->code = "client";
      }
      $data['message'] = $e->getMessage();
      $fault->fault->string = $data['message'];
      if($this->debug) {
      	$data['trace'] = $e->getTrace();
      	$fault->fault->detail = $data;
      }
      return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode($fault));
   }
}

?>
