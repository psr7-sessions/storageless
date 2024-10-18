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
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Lcobucci\JWT\Configuration as JwtConfig;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSR7Sessions\Storageless\Http\Configuration;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Session\SessionInterface;

require_once __DIR__ . '/../vendor/autoload.php';

// the example uses a symmetric key, but asymmetric keys can also be used.
// $privateKey = new Key('file://private_key.pem');
// $publicKey = new Key('file://public_key.pem');

// simply run `php -S localhost:9999 index.php`
// then point your browser at `http://localhost:9999/`

$clock             = SystemClock::fromUTC();
$sessionMiddleware = new SessionMiddleware(
    (new Configuration(
        JwtConfig::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText('c9UA8QKLSmDEn4DhNeJIad/4JugZd/HvrjyKrS0jOes='), // // signature key (important: change this to your own)
        ),
    ))->withCookie(
        SetCookie::create('an-example-cookie-name')
            ->withSecure(false) // false on purpose, unless you have https locally
            ->withHttpOnly(true)
            ->withPath('/'),
    )->withIdleTimeout(1200), // 20 minutes
);

$myMiddleware = new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        assert($session instanceof SessionInterface);

        $counterValue = $session->get('counter', 0);

        assert(is_int($counterValue));

        $counterValue += 1;

        $session->set('counter', $counterValue);

        $response = new Response();

        $response
            ->getBody()
            ->write('Counter Value: ' . $counterValue);

        return $response;
    }
};

(new SapiEmitter())
    ->emit($sessionMiddleware->process(ServerRequestFactory::fromGlobals(), $myMiddleware));
