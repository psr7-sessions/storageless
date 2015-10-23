<?php

use Zend\Expressive\AppFactory;
use StoragelessSession\Http\SessionMiddleware;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Parser;
use StoragelessSession\Session\Data;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
// $privateKey = new Key('file://private_key.pem');
// $publicKey = new Key('file://public_key.pem');
// the example uses a symmetric key, but asymmetric keys can also be used.
$privateKey = $publicKey = 'I do not care';

$app->pipe(new SessionMiddleware(
    new Sha256(),
    $privateKey,
    $publicKey,
    \Dflydev\FigCookies\SetCookie::create('foo'),
    new Parser(),
    14400
));
$app->pipe($api = AppFactory::create());

$api->get('/get', function ($request, ResponseInterface $response, $next) {
    /**
     * @var Data $container
     */
    $container = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
    $container->set('hello', $container->has('hello') ? $container->get('hello') + 1 : 0);

    return $response->write($container->get('hello'));
});

$app->run();
