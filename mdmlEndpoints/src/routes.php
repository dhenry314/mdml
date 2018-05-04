<?php
// Routes


//Enable CORS
$app->options('/{name:.+}', \Core\Http\CorsAction::class);

$app->any('/{path:.*}', function($request,$response,$args) {
    global $allowablePaths;
    if(strstr($args['path'],'loggingService')) {
		$ep = new \LoggingService($request,$response,$allowablePaths,$args);
	} else {
		$ep = new \EndpointController($request,$response,$allowablePaths,$args);
	}
    if(!$content = $ep->resolve()) {
		return $response->withStatus(404);
    } elseif(is_object($content)) {
		if(property_exists($content,'errorCode')) {
			return $response->withStatus($content->errorCode);
		}
    }
    session_write_close();
    $data = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK );
    return $response->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->write($data);
});
