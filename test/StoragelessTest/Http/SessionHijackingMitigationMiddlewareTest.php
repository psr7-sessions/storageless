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

namespace PSR7SessionsTest\Storageless\Http;

use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSR7Sessions\Storageless\Http\SessionHijackingMitigationMiddleware;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Session\SessionInterface;
use RuntimeException;

use function assert;
use function base64_encode;
use function random_bytes;
use function uniqid;

/** @covers \PSR7Sessions\Storageless\Http\SessionHijackingMitigationMiddleware */
final class SessionHijackingMitigationMiddlewareTest extends TestCase
{
    public const DEFAULT_SERVER_PARAMS = [
        SessionHijackingMitigationMiddleware::SERVER_PARAM_REMOTE_ADDR => '1.1.1.1',
        SessionHijackingMitigationMiddleware::SERVER_PARAM_USER_AGENT => 'Firefox',
    ];

    private SessionMiddleware $sessionMiddleware;

    protected function setUp(): void
    {
        $this->sessionMiddleware = SessionMiddleware::fromSymmetricKeyDefaults(
            self::makeRandomSymmetricKey(),
            100,
        );
    }

    public function testRequireRemoteAddrToBeSetInTheRequest(): void
    {
        $hijackingMitigationMiddleware = new SessionHijackingMitigationMiddleware();

        $serverParams = self::DEFAULT_SERVER_PARAMS;
        unset($serverParams[SessionHijackingMitigationMiddleware::SERVER_PARAM_REMOTE_ADDR]);

        $this->expectException(RuntimeException::class);

        $this->sessionMiddleware->process(
            new ServerRequest($serverParams),
            $this->fakeDelegate(function (ServerRequestInterface $request) use ($hijackingMitigationMiddleware): ResponseInterface {
                return $hijackingMitigationMiddleware->process(
                    $request,
                    $this->createMock(RequestHandlerInterface::class),
                );
            }),
        );
    }

    public function testRequireRemoteAddrToBeNotEmptyInTheRequest(): void
    {
        $hijackingMitigationMiddleware = new SessionHijackingMitigationMiddleware();

        $serverParams = self::DEFAULT_SERVER_PARAMS;

        $serverParams[SessionHijackingMitigationMiddleware::SERVER_PARAM_REMOTE_ADDR] = '';

        $this->expectException(RuntimeException::class);

        $this->sessionMiddleware->process(
            new ServerRequest($serverParams),
            $this->fakeDelegate(function (ServerRequestInterface $request) use ($hijackingMitigationMiddleware): ResponseInterface {
                return $hijackingMitigationMiddleware->process(
                    $request,
                    $this->createMock(RequestHandlerInterface::class),
                );
            }),
        );
    }

    public function testRequireUserAgentToBeSetInTheRequest(): void
    {
        $hijackingMitigationMiddleware = new SessionHijackingMitigationMiddleware();

        $serverParams = self::DEFAULT_SERVER_PARAMS;
        unset($serverParams[SessionHijackingMitigationMiddleware::SERVER_PARAM_USER_AGENT]);

        $this->expectException(RuntimeException::class);

        $this->sessionMiddleware->process(
            new ServerRequest($serverParams),
            $this->fakeDelegate(function (ServerRequestInterface $request) use ($hijackingMitigationMiddleware): ResponseInterface {
                return $hijackingMitigationMiddleware->process(
                    $request,
                    $this->createMock(RequestHandlerInterface::class),
                );
            }),
        );
    }

    public function testRequireUserAgentToBeNotEmptyInTheRequest(): void
    {
        $hijackingMitigationMiddleware = new SessionHijackingMitigationMiddleware();

        $serverParams = self::DEFAULT_SERVER_PARAMS;

        $serverParams[SessionHijackingMitigationMiddleware::SERVER_PARAM_USER_AGENT] = '';

        $this->expectException(RuntimeException::class);

        $this->sessionMiddleware->process(
            new ServerRequest($serverParams),
            $this->fakeDelegate(function (ServerRequestInterface $request) use ($hijackingMitigationMiddleware): ResponseInterface {
                return $hijackingMitigationMiddleware->process(
                    $request,
                    $this->createMock(RequestHandlerInterface::class),
                );
            }),
        );
    }

    public function testWillClearSessionWhenIpChanges(): void
    {
        $hijackingMitigationMiddleware = new SessionHijackingMitigationMiddleware();

        $sessionKey   = 'my_key';
        $sessionValue = uniqid('my_value_');

        $firstRequest = new ServerRequest(self::DEFAULT_SERVER_PARAMS);

        $firstResponse = $this->sessionMiddleware->process(
            $firstRequest,
            $this->fakeDelegate(function (ServerRequestInterface $request) use ($hijackingMitigationMiddleware, $sessionKey, $sessionValue): ResponseInterface {
                return $hijackingMitigationMiddleware->process(
                    $request,
                    $this->fakeDelegate(static function (ServerRequestInterface $request) use ($sessionKey, $sessionValue): ResponseInterface {
                        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                        assert($session instanceof SessionInterface);

                        $session->set($sessionKey, $sessionValue);

                        return new Response();
                    }),
                );
            }),
        );

        $secondRequest = new ServerRequest(self::DEFAULT_SERVER_PARAMS);
        $secondRequest = $this->requestWithResponseCookies($secondRequest, $firstResponse);

        $this->sessionMiddleware->process(
            $secondRequest,
            $this->fakeDelegate(function (ServerRequestInterface $request) use ($hijackingMitigationMiddleware, $sessionKey, $sessionValue): ResponseInterface {
                return $hijackingMitigationMiddleware->process(
                    $request,
                    $this->fakeDelegate(static function (ServerRequestInterface $request) use ($sessionKey, $sessionValue): ResponseInterface {
                        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                        assert($session instanceof SessionInterface);

                        self::assertFalse($session->isEmpty());
                        self::assertSame($sessionValue, $session->get($sessionKey));

                        return new Response();
                    }),
                );
            }),
        );

        $serverParamsWithNewIp                                                                 = self::DEFAULT_SERVER_PARAMS;
        $serverParamsWithNewIp[SessionHijackingMitigationMiddleware::SERVER_PARAM_REMOTE_ADDR] = '2.2.2.2';

        $thirdRequest = new ServerRequest($serverParamsWithNewIp);
        $thirdRequest = $this->requestWithResponseCookies($thirdRequest, $firstResponse);

        $this->sessionMiddleware->process(
            $thirdRequest,
            $this->fakeDelegate(function (ServerRequestInterface $request) use ($hijackingMitigationMiddleware): ResponseInterface {
                return $hijackingMitigationMiddleware->process(
                    $request,
                    $this->emptyValidationMiddleware(),
                );
            }),
        );
    }

    public function testWillClearSessionWhenUserAgentChanges(): void
    {
        $hijackingMitigationMiddleware = new SessionHijackingMitigationMiddleware();

        $sessionKey   = 'my_key';
        $sessionValue = uniqid('my_value_');

        $firstRequest = new ServerRequest(self::DEFAULT_SERVER_PARAMS);

        $firstResponse = $this->sessionMiddleware->process(
            $firstRequest,
            $this->fakeDelegate(function (ServerRequestInterface $request) use ($hijackingMitigationMiddleware, $sessionKey, $sessionValue): ResponseInterface {
                return $hijackingMitigationMiddleware->process(
                    $request,
                    $this->fakeDelegate(static function (ServerRequestInterface $request) use ($sessionKey, $sessionValue): ResponseInterface {
                        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                        assert($session instanceof SessionInterface);

                        $session->set($sessionKey, $sessionValue);

                        return new Response();
                    }),
                );
            }),
        );

        $secondRequest = new ServerRequest(self::DEFAULT_SERVER_PARAMS);
        $secondRequest = $this->requestWithResponseCookies($secondRequest, $firstResponse);

        $this->sessionMiddleware->process(
            $secondRequest,
            $this->fakeDelegate(function (ServerRequestInterface $request) use ($hijackingMitigationMiddleware, $sessionKey, $sessionValue): ResponseInterface {
                return $hijackingMitigationMiddleware->process(
                    $request,
                    $this->fakeDelegate(static function (ServerRequestInterface $request) use ($sessionKey, $sessionValue): ResponseInterface {
                        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                        assert($session instanceof SessionInterface);

                        self::assertFalse($session->isEmpty());
                        self::assertSame($sessionValue, $session->get($sessionKey));

                        return new Response();
                    }),
                );
            }),
        );

        $serverParamsWithNewUserAgent                                                                = self::DEFAULT_SERVER_PARAMS;
        $serverParamsWithNewUserAgent[SessionHijackingMitigationMiddleware::SERVER_PARAM_USER_AGENT] = 'Chrome';

        $thirdRequest = new ServerRequest($serverParamsWithNewUserAgent);
        $thirdRequest = $this->requestWithResponseCookies($thirdRequest, $firstResponse);

        $this->sessionMiddleware->process(
            $thirdRequest,
            $this->fakeDelegate(function (ServerRequestInterface $request) use ($hijackingMitigationMiddleware): ResponseInterface {
                return $hijackingMitigationMiddleware->process(
                    $request,
                    $this->emptyValidationMiddleware(),
                );
            }),
        );
    }

    public function testWillNotAddFingerprintWhenSessionIsEmpty(): void
    {
        $hijackingMitigationMiddleware = new SessionHijackingMitigationMiddleware();

        $response = $this->sessionMiddleware->process(
            new ServerRequest(self::DEFAULT_SERVER_PARAMS),
            $this->fakeDelegate(function (ServerRequestInterface $request) use ($hijackingMitigationMiddleware): ResponseInterface {
                return $hijackingMitigationMiddleware->process(
                    $request,
                    $this->emptyValidationMiddleware(),
                );
            }),
        );

        self::assertNull($this->getCookie($response)->getValue());
    }

    public function testFingerprintShouldNotContainsReadableData(): void
    {
        $hijackingMitigationMiddleware = new SessionHijackingMitigationMiddleware();

        $response = $this->sessionMiddleware->process(
            new ServerRequest(self::DEFAULT_SERVER_PARAMS),
            $this->fakeDelegate(function (ServerRequestInterface $request) use ($hijackingMitigationMiddleware): ResponseInterface {
                return $hijackingMitigationMiddleware->process(
                    $request,
                    $this->fakeDelegate(static function (ServerRequestInterface $request): ResponseInterface {
                        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                        assert($session instanceof SessionInterface);

                        $session->set('foo', 'bar');

                        return new Response();
                    }),
                );
            }),
        );

        $token = $this->getCookie($response)->getValue();

        self::assertIsString($token);
        self::assertTrue($token !== '');
        $parsedToken = (new Parser(new JoseEncoder()))->parse($token);
        self::assertInstanceOf(Plain::class, $parsedToken);

        $sessionData = $parsedToken->claims()->get(SessionMiddleware::SESSION_CLAIM);
        self::assertIsArray($sessionData);
        self::assertArrayHasKey(SessionHijackingMitigationMiddleware::SESSION_KEY, $sessionData);

        $fingerprint = $sessionData[SessionHijackingMitigationMiddleware::SESSION_KEY];
        self::assertIsString($fingerprint);
        self::assertNotEmpty($fingerprint);
        self::assertStringNotContainsString(self::DEFAULT_SERVER_PARAMS[SessionHijackingMitigationMiddleware::SERVER_PARAM_REMOTE_ADDR], $fingerprint);
        self::assertStringNotContainsString(self::DEFAULT_SERVER_PARAMS[SessionHijackingMitigationMiddleware::SERVER_PARAM_USER_AGENT], $fingerprint);
    }

    private function fakeDelegate(callable $callback): RequestHandlerInterface
    {
        $middleware = $this->createMock(RequestHandlerInterface::class);

        $middleware
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback($callback)
            ->with(self::isInstanceOf(RequestInterface::class));

        return $middleware;
    }

    private function requestWithResponseCookies(ServerRequestInterface $request, ResponseInterface $response): ServerRequestInterface
    {
        return $request->withCookieParams([
            SessionMiddleware::DEFAULT_COOKIE => $this->getCookie($response)->getValue(),
        ]);
    }

    private function getCookie(ResponseInterface $response, string $name = SessionMiddleware::DEFAULT_COOKIE): SetCookie
    {
        return FigResponseCookies::get($response, $name);
    }

    private function emptyValidationMiddleware(): RequestHandlerInterface
    {
        return $this->fakeDelegate(
            static function (ServerRequestInterface $request) {
                $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

                self::assertInstanceOf(SessionInterface::class, $session);
                self::assertTrue($session->isEmpty());

                return new Response();
            },
        );
    }

    private static function makeRandomSymmetricKey(): InMemory
    {
        return InMemory::plainText('test-key_' . base64_encode(random_bytes(128)));
    }
}
