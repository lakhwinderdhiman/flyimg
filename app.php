<?php

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\Routing\RouteCollection;

$app = new Silex\Application();

/**
 * Application parameters
 */
$app['params'] = yaml_parse(file_get_contents(__DIR__.'/config/parameters.yml'));


$app['env'] = $_ENV['env'] ?: 'dev';
$exceptionHandlerFunction = function (\Exception $e) {
    $out = fopen('php://stdout', 'w');
    fputs(
        $out,
        "Message: {$e->getMessage()} \nFile: {$e->getFile()}\nLine: {$e->getLine()}\nTrace: {$e->getTraceAsString()}"
    );
    fclose($out);
};

ErrorHandler::register();
$exceptionHandler = ExceptionHandler::register($app['params']['debug']);
$exceptionHandler->setHandler($exceptionHandlerFunction);

if ('test' !== $app['env']) {
    $app->error($exceptionHandlerFunction);
}

/**
 * Define Constants && Load parameters files
 */
define('UPLOAD_WEB_DIR', 'uploads/');
define('UPLOAD_DIR', __DIR__.'/web/'.UPLOAD_WEB_DIR);
define('TMP_DIR', __DIR__.'/var/tmp/');
define('ROOT_DIR', __DIR__);

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
if (!is_dir(TMP_DIR)) {
    mkdir(TMP_DIR, 0777, true);
}

/**
 * Routes
 */
$routesResolver = new \Core\Resolver\RoutesResolver();
$app['routes'] = $app->extend(
    'routes',
    function (RouteCollection $routes) use ($routesResolver) {
        return $routesResolver->parseRoutesFromYamlFile($routes, __DIR__.'/config/routes.yml');
    }
);

/** Register Storage provider */

switch ($app['params']['storage_system']) {
    case 's3':
        $app->register(new \Core\StorageProvider\S3StorageProvider());
        break;
    case 'local':
    default:
        $app->register(new \Core\StorageProvider\LocalStorageProvider());
        break;
}

/**
 * Controller Resolver
 *
 * @param \Silex\Application $app
 *
 * @return \Core\Resolver\ControllerResolver
 */
$app['resolver'] = function (\Silex\Application $app) {
    return new \Core\Resolver\ControllerResolver($app, $app['logger']);
};

/**
 * Register Image Handler
 *
 * @param \Silex\Application $app
 *
 * @return \Core\Handler\ImageHandler
 */
$app['image.handler'] = function (\Silex\Application $app) {
    return new \Core\Handler\ImageHandler(
        $app['flysystems']['upload_dir'],
        $app['params']
    );
};

if (!empty($argv[1]) && !empty($argv[2]) && $argv[1] == 'encrypt') {
    printf("\n%s\n", $app['image.handler']->getSecurityHandler()->encrypt($argv[2]));
    exit;
}

/** debug conf */
$app['debug'] = $app['params']['debug'];
return $app;
