<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

declare(strict_types=1);

use Dflydev\FigCookies\SetCookie;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use StoragelessSession\Http\SessionMiddleware;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Diactoros\ServerRequestFactory;

require_once __DIR__ . '/../vendor/autoload.php';

// the example uses a symmetric key, but asymmetric keys can also be used.
// $privateKey = new Key('file://private_key.pem');
// $publicKey = new Key('file://public_key.pem');

// simply run `php -S localhost:8888 index.php`
// then point your browser at `http://localhost:8888/`

$sessionMiddleware = new SessionMiddleware(
    new Sha256(),
    'a very complex symmetric key',
    'a very complex symmetric key',
    SetCookie::create('an-example-cookie-name')
        ->withSecure(false) // false on purpose, unless you have https locally
        ->withHttpOnly(true),
    new Parser(),
    1200 // 20 minutes
);
$myMiddleware = function (ServerRequestInterface $request, ResponseInterface $response) : ResponseInterface {
    /* @var \StoragelessSession\Session\SessionInterface $container */
    $container = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
    $container->set('counter', $container->get('counter', 0) + 1);

    $response
        ->getBody()
        ->write('Counter Value: ' . $container->get('counter'));

    return $response;
};

(new SapiEmitter())
    ->emit($sessionMiddleware(ServerRequestFactory::fromGlobals(), new Response(), $myMiddleware));
