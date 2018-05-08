<?php
set_time_limit(0);
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Firebase\JWT\JWT;

require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';

ini_set('memory_limit','256M');

$reqPath = str_replace($config['BASE_PATH'], '', $_SERVER['REQUEST_URI']);
//remove the request_string from the reqPath
$reqParts = explode('?',$reqPath);
$reqPath = array_shift($reqParts);
if($reqPath=='/login' || $reqPath=='login' || $reqPath=='login/') {
  if(array_key_exists('msjwt',$_REQUEST)) {
  	$response = array('ERROR'=>'Incorrect token!');
  	if($data = \User::tokenauth($config,$_REQUEST['msjwt'])) {
    		$jwt = JWT::encode($data, $config["JWT_SECRET"]);
    		$response = array("JWT"=>$jwt,"instructions"=>"Add an Authorization header in all requests with the value 'Bearer jwt'");
  	}
  } else {
  	$response = array('ERROR'=>'Username/password combination incorrect.');
  	if($data = \User::auth($config,$_GET['username'],$_GET['password'])) {
    		$jwt = JWT::encode($data, $config["JWT_SECRET"]);
    		$response = array("JWT"=>$jwt,"instructions"=>"Add an Authorization header in all requests with the value 'Bearer jwt'");
  	}
  }
  header('Content-type: application/json');
  echo json_encode( $response );
  exit;
}

#session_start();
// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';
$app = new \Slim\App($settings);
$c = $app->getContainer();
$c['errorHandler'] = function ($c) {
    return new MDMLExceptionHandler();
};

$app->add(new \Slim\Middleware\JwtAuthentication([
    "secret" => $config["JWT_SECRET"],
    "secure" => false,
    "error" => function ($request, $response, $arguments) {
        $data["status"] = "error";
        $data["message"] = $arguments["message"];
        return $response
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
]));

$jwtPayload = \User::getJWTPayload($config);
$allowablePaths = $jwtPayload->aud;


// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// Register routes
require __DIR__ . '/../src/routes.php';

// Run app
$app->run();
