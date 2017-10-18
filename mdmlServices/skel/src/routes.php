<?php
// Routes

$app->any('/serviceCall', function($request,$response,$args) {
    global $config,$allowablePaths;
    //unset sensitive information in the config
    unset($config['JWT_SECRET']);
    unset($config['auth']);
    $service = new \mdml\ServiceController($config,$request,$response,$allowablePaths);
    if(!$content = $service->resolve()) {
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

