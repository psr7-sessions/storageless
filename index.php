<?php

use Zend\Expressive\AppFactory;
use StoragelessSession\Http\SessionMiddleware;
//use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Key;
use StoragelessSession\Session\Data;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
// $privateKey = new Key('file://private_key.pem');
// $publicKey = new Key('file://public_key.pem');
$privateKey = $publicKey = new Key('I do not care');

$app->pipe(new SessionMiddleware(new Sha256(), $privateKey, $publicKey, new Parser()));
$app->pipe($api = AppFactory::create());

$api->get('/', function ($request, $response, $next) {
    /**
     * @var Data $container
     */
    $container = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
    $container->set('hello', 'world');
});

$api->get('/get', function ($request, ResponseInterface $response, $next) {
    /**
     * @var Data $container
     */
    $container = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
    $container->set('hello', $container->get('hello') + 1);

    return $response->write($container->get('hello'));
});

$api->get('/test', function ($request, ResponseInterface $response, $next) {
    ini_set('session.save_path', '/root/sessions');
    session_start();

    $_SESSION['hello'] = @$_SESSION['hello'] + 1;

    return $response->write($_SESSION['hello']);
});

$api->get('/phpinfo', function ($request, ResponseInterface $response, $next) {
    ini_set('session.save_path', '/root/sessions');
    phpinfo();
    die();
});



$app->run();