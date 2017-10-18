<?php

class MDMLExceptionHandler {
   public function __invoke($request, $response, $e) {
     // Add the exception class name, message and stack trace to response
      $data['exception'] = get_class($e); // Reflection might be better here
      $data['message'] = $e->getMessage();
      $data['trace'] = $e->getTrace();
      return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode($data));
   }
}

?>
