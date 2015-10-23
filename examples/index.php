<?php

use Zend\Expressive\AppFactory;
use StoragelessSession\Http\SessionMiddleware;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Parser;
use StoragelessSession\Session\Data;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/../vendor/autoload.php';

// To run this example, you will need to run (in the project directory)
// composer require zendframework/zend-expressive
// composer require zendframework/zend-servicemanager

// the example uses a symmetric key, but asymmetric keys can also be used.
// $privateKey = new Key('file://private_key.pem');
// $publicKey = new Key('file://public_key.pem');
$privateKey = $publicKey = 'I do not care';

$app = AppFactory::create();

$app
    ->pipe(new SessionMiddleware(
        new Sha256(),
        $privateKey,
        $publicKey,
        \Dflydev\FigCookies\SetCookie::create('foo'),
        new Parser(),
        14400
    ))
    ->pipe($api = AppFactory::create())
    ->get('/get', function ($request, ResponseInterface $response, $next) {
        /* @var Data $container */
        $container = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        $container->set('hello', $container->has('hello') ? $container->get('hello') + 1 : 0);

        return $response->write($container->get('hello'));
    });

$app->run();
