<?php
set_time_limit(0);
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Firebase\JWT\JWT;

require __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config.php';

ini_set('memory_limit','256M');

// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';
$app = new \Slim\App($settings);
$c = $app->getContainer();
$c['errorHandler'] = function ($c) {
    return new \mdml\MDMLExceptionHandler();
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

$jwtPayload = \mdml\User::getJWTPayload($config);
$allowablePaths = $jwtPayload->aud;


// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// Register routes
require __DIR__ . '/../src/routes.php';

// Run app
$app->run();
