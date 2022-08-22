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

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Parser as ParserInterface;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\RegisteredClaims;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Session\DefaultSessionData;
use PSR7Sessions\Storageless\Session\SessionInterface;
use PSR7SessionsTest\Storageless\Asset\MutableBadCookie;
use ReflectionProperty;

use function assert;
use function base64_encode;
use function date_default_timezone_get;
use function random_bytes;
use function random_int;
use function time;
use function uniqid;

final class SessionMiddlewareTest extends TestCase
{
    /**
     * @param callable(): SessionMiddleware $middlewareFactory
     *
     * @dataProvider defaultMiddlewaresProvider
     * @group #46
     */
    public function testDefaultMiddlewareConfiguresASecureCookie(callable $middlewareFactory): void
    {
        $middleware = $middlewareFactory();
        $response   = $middleware->process(new ServerRequest(), $this->writingMiddleware());

        $cookie = $this->getCookie($response);

        self::assertCookieIsSecure($cookie);
    }

    public function testProvideADefaultSecureCookieForCustomConstructors(): void
    {
        $cookie = SessionMiddleware::buildDefaultCookie();

        self::assertCookieIsSecure($cookie);
    }

    /**
     * @param callable(): SessionMiddleware $middlewareFactory
     *
     * @dataProvider validMiddlewaresProvider
     */
    public function testSkipsInjectingSessionCookieOnEmptyContainer(callable $middlewareFactory): void
    {
        $middleware = $middlewareFactory();
        $response   = $this->ensureSameResponse($middleware, new ServerRequest(), $this->emptyValidationMiddleware());

        self::assertNull($this->getCookie($response)->getValue());
    }

    /**
     * @param callable(): SessionMiddleware $middlewareFactory
     *
     * @dataProvider validMiddlewaresProvider
     */
    public function testExtractsSessionContainerFromEmptyRequest(callable $middlewareFactory): void
    {
        $middleware = $middlewareFactory();
        $this->ensureSameResponse($middleware, new ServerRequest(), $this->emptyValidationMiddleware());
    }

    /**
     * @param callable(): SessionMiddleware $middlewareFactory
     *
     * @dataProvider validMiddlewaresProvider
     */
    public function testInjectsSessionInResponseCookies(callable $middlewareFactory): void
    {
        $middleware      = $middlewareFactory();
        $initialResponse = new Response();
        $response        = $middleware->process(new ServerRequest(), $this->writingMiddleware());

        self::assertNotSame($initialResponse, $response);
        self::assertEmpty($this->getCookie($response, 'non-existing')->getValue());

        $token = $this->getCookie($response)->getValue();

        self::assertIsString($token);
        $parsedToken = (new Parser(new JoseEncoder()))->parse($token);
        self::assertInstanceOf(Plain::class, $parsedToken);
        self::assertEquals(['foo' => 'bar'], $parsedToken->claims()->get('session-data'));
    }

    /**
     * @param callable(): SessionMiddleware $middlewareFactory
     *
     * @dataProvider validMiddlewaresProvider
     */
    public function testSessionContainerCanBeReusedOverMultipleRequests(callable $middlewareFactory): void
    {
        $middleware   = $middlewareFactory();
        $sessionValue = uniqid('', true);

        $checkingMiddleware = $this->fakeDelegate(
            static function (ServerRequestInterface $request) use ($sessionValue) {
                $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                assert($session instanceof SessionInterface);

                self::assertSame($sessionValue, $session->get('foo'));
                self::assertFalse($session->hasChanged());

                $session->set('foo', $sessionValue . 'changed');

                self::assertTrue(
                    $session->hasChanged(),
                    'ensuring that the cookie is sent again: '
                    . 'non-modified session containers are not to be re-serialized into a token'
                );

                return new Response();
            }
        );

        $firstResponse = $middleware->process(new ServerRequest(), $this->writingMiddleware($sessionValue));

        $response = $middleware->process(
            $this->requestWithResponseCookies($firstResponse),
            $checkingMiddleware
        );

        self::assertNotSame($response, $firstResponse);
    }

    /**
     * @param callable(): SessionMiddleware $middlewareFactory
     *
     * @dataProvider validMiddlewaresProvider
     */
    public function testSessionContainerCanBeCreatedEvenIfTokenDataIsMalformed(callable $middlewareFactory): void
    {
        $middleware   = $middlewareFactory();
        $sessionValue = uniqid('not valid session data', true);

        $checkingMiddleware = $this->fakeDelegate(
            static function (ServerRequestInterface $request) use ($sessionValue) {
                $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                assert($session instanceof SessionInterface);

                self::assertSame($sessionValue, $session->get('scalar'));
                self::assertFalse($session->hasChanged());

                return new Response();
            }
        );

        $this->createTokenWithCustomClaim(
            $middleware,
            new DateTimeImmutable('-1 day'),
            new DateTimeImmutable('+1 day'),
            'not valid session data'
        );

        $middleware->process(
            (new ServerRequest())
                ->withCookieParams([
                    SessionMiddleware::DEFAULT_COOKIE => $this->createTokenWithCustomClaim(
                        $middleware,
                        new DateTimeImmutable('-1 day'),
                        new DateTimeImmutable('+1 day'),
                        $sessionValue
                    ),
                ]),
            $checkingMiddleware
        );
    }

    /**
     * @param callable(): SessionMiddleware $middlewareFactory
     *
     * @dataProvider validMiddlewaresProvider
     */
    public function testWillIgnoreRequestsWithExpiredTokens(callable $middlewareFactory): void
    {
        $middleware   = $middlewareFactory();
        $expiredToken = (new ServerRequest())
            ->withCookieParams([
                SessionMiddleware::DEFAULT_COOKIE => $this->createToken(
                    $middleware,
                    new DateTimeImmutable('-1 day'),
                    new DateTimeImmutable('-2 day')
                ),
            ]);

        $this->ensureSameResponse($middleware, $expiredToken, $this->emptyValidationMiddleware());
    }

    public function testWillIgnoreRequestsWithNonPlainTokens(): void
    {
        $unknownTokenType = $this->createMock(Token::class);
        $fakeParser       = $this->createMock(ParserInterface::class);
        $configuration    = Configuration::forUnsecuredSigner();

        $fakeParser->expects(self::atLeastOnce())
            ->method('parse')
            ->with('THE_COOKIE')
            ->willReturn($unknownTokenType);
        $configuration->setParser($fakeParser);

        $this->ensureSameResponse(
            new SessionMiddleware(
                $configuration,
                SetCookie::create('COOKIE_NAME'),
                100,
                new FrozenClock(new DateTimeImmutable())
            ),
            (new ServerRequest())
                ->withCookieParams(['COOKIE_NAME' => 'THE_COOKIE']),
            $this->emptyValidationMiddleware()
        );
    }

    /**
     * @param callable(): SessionMiddleware $middlewareFactory
     *
     * @dataProvider validMiddlewaresProvider
     */
    public function testWillIgnoreRequestsWithTokensFromFuture(callable $middlewareFactory): void
    {
        $middleware    = $middlewareFactory();
        $tokenInFuture = (new ServerRequest())
            ->withCookieParams([
                SessionMiddleware::DEFAULT_COOKIE => $this->createToken(
                    $middleware,
                    new DateTimeImmutable('+1 day'),
                    new DateTimeImmutable('-2 day')
                ),
            ]);

        $this->ensureSameResponse($middleware, $tokenInFuture, $this->emptyValidationMiddleware());
    }

    /**
     * @param callable(): SessionMiddleware $middlewareFactory
     *
     * @dataProvider validMiddlewaresProvider
     */
    public function testWillIgnoreUnSignedTokens(callable $middlewareFactory): void
    {
        $middleware    = $middlewareFactory();
        $configuration = Configuration::forUnsecuredSigner();
        $unsignedToken = (new ServerRequest())
            ->withCookieParams([
                SessionMiddleware::DEFAULT_COOKIE => $configuration->builder()
                    ->issuedAt(new DateTimeImmutable('-1 day'))
                    ->canOnlyBeUsedAfter(new DateTimeImmutable('-1 day'))
                    ->expiresAt(new DateTimeImmutable('+1 day'))
                    ->withClaim(SessionMiddleware::SESSION_CLAIM, DefaultSessionData::fromTokenData(['foo' => 'bar']))
                    ->getToken($configuration->signer(), $configuration->signingKey())
                    ->toString(),
            ]);

        $this->ensureSameResponse($middleware, $unsignedToken, $this->emptyValidationMiddleware());
    }

    /**
     * @param callable(): SessionMiddleware $middlewareFactory
     *
     * @dataProvider validMiddlewaresProvider
     */
    public function testWillIgnoreSignedTokensWithoutIssuedAt(callable $middlewareFactory): void
    {
        $middleware    = $middlewareFactory();
        $configuration = $this->getJwtConfiguration($middleware);
        $unsignedToken = (new ServerRequest())
            ->withCookieParams([
                SessionMiddleware::DEFAULT_COOKIE => $configuration->builder()
                    ->canOnlyBeUsedAfter(new DateTimeImmutable('-1 day'))
                    ->expiresAt(new DateTimeImmutable('+1 day'))
                    ->withClaim(SessionMiddleware::SESSION_CLAIM, DefaultSessionData::fromTokenData(['foo' => 'bar']))
                    ->getToken($configuration->signer(), $configuration->signingKey())
                    ->toString(),
            ]);

        $this->ensureSameResponse($middleware, $unsignedToken, $this->emptyValidationMiddleware());
    }

    public function testWillRefreshTokenWithIssuedAtExactlyAtTokenRefreshTimeThreshold(): void
    {
        // forcing ourselves to think of time as a mutable value:
        $time  = time() + random_int(-100, +100);
        $now   = new DateTimeImmutable('@' . $time);
        $clock = new FrozenClock($now);
        $key   = self::makeRandomSymmetricKey();

        $configuration = Configuration::forAsymmetricSigner(
            new Sha256(),
            $key,
            $key
        );
        $middleware    = new SessionMiddleware(
            $configuration,
            SetCookie::create(SessionMiddleware::DEFAULT_COOKIE),
            1000,
            $clock,
            100
        );

        $requestWithTokenIssuedInThePast = (new ServerRequest())
            ->withCookieParams([
                SessionMiddleware::DEFAULT_COOKIE => $configuration->builder()
                    ->expiresAt(new DateTimeImmutable('@' . ($time + 10000)))
                    ->issuedAt(new DateTimeImmutable('@' . ($time - 100)))
                    ->canOnlyBeUsedAfter(new DateTimeImmutable('@' . ($time - 100)))
                    ->withClaim(SessionMiddleware::SESSION_CLAIM, DefaultSessionData::fromTokenData(['foo' => 'bar']))
                    ->getToken($configuration->signer(), $configuration->signingKey())
                    ->toString(),
            ]);

        $tokenString = $this
            ->getCookie($middleware->process($requestWithTokenIssuedInThePast, $this->fakeDelegate(static function () {
                return new Response();
            })))
            ->getValue();

        self::assertIsString($tokenString);

        $token = (new Parser(new JoseEncoder()))->parse($tokenString);
        self::assertInstanceOf(Plain::class, $token);
        self::assertEquals($now, $token->claims()->get(RegisteredClaims::ISSUED_AT), 'Token was refreshed');
    }

    /**
     * @param callable(): SessionMiddleware $middlewareFactory
     *
     * @dataProvider validMiddlewaresProvider
     */
    public function testWillSkipInjectingSessionCookiesWhenSessionIsNotChanged(callable $middlewareFactory): void
    {
        $middleware = $middlewareFactory();
        $this->ensureSameResponse(
            $middleware,
            $this->requestWithResponseCookies(
                $middleware->process(new ServerRequest(), $this->writingMiddleware())
            ),
            $this->fakeDelegate(
                static function (ServerRequestInterface $request) {
                    $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                    assert($session instanceof SessionInterface);

                    // note: we set the same data just to make sure that we are indeed interacting with the session
                    $session->set('foo', 'bar');

                    self::assertFalse($session->hasChanged());

                    return new Response();
                }
            )
        );
    }

    /**
     * @param callable(): SessionMiddleware $middlewareFactory
     *
     * @dataProvider validMiddlewaresProvider
     */
    public function testWillSendExpirationCookieWhenSessionContentsAreCleared(callable $middlewareFactory): void
    {
        $middleware = $middlewareFactory();
        $this->ensureClearsSessionCookie(
            $middleware,
            $this->requestWithResponseCookies(
                $middleware->process(new ServerRequest(), $this->writingMiddleware())
            ),
            $this->fakeDelegate(
                static function (ServerRequestInterface $request) {
                    $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                    assert($session instanceof SessionInterface);

                    $session->clear();

                    return new Response();
                }
            )
        );
    }

    /**
     * @param callable(): SessionMiddleware $middlewareFactory
     *
     * @dataProvider validMiddlewaresProvider
     */
    public function testWillIgnoreMalformedTokens(callable $middlewareFactory): void
    {
        $middleware = $middlewareFactory();
        $this->ensureSameResponse(
            $middleware,
            (new ServerRequest())->withCookieParams([SessionMiddleware::DEFAULT_COOKIE => 'malformed content']),
            $this->emptyValidationMiddleware()
        );
    }

    public function testRejectsTokensWithInvalidSignature(): void
    {
        $middleware               = new SessionMiddleware(
            Configuration::forSymmetricSigner(
                new Sha256(),
                self::makeRandomSymmetricKey()
            ),
            SetCookie::create(SessionMiddleware::DEFAULT_COOKIE),
            100,
            new SystemClock(new DateTimeZone(date_default_timezone_get()))
        );
        $middlewareWithAlteredKey = new SessionMiddleware(
            Configuration::forSymmetricSigner(
                new Sha256(),
                self::makeRandomSymmetricKey()
            ),
            SetCookie::create(SessionMiddleware::DEFAULT_COOKIE),
            100,
            new SystemClock(new DateTimeZone(date_default_timezone_get()))
        );

        $this->ensureSameResponse(
            $middlewareWithAlteredKey,
            $this->requestWithResponseCookies(
                $middleware->process(new ServerRequest(), $this->writingMiddleware())
            ),
            $this->emptyValidationMiddleware()
        );
    }

    public function testAllowsModifyingCookieDetails(): void
    {
        $defaultCookie = SetCookie::create('a-different-cookie-name')
            ->withDomain('foo.bar')
            ->withPath('/yadda')
            ->withHttpOnly(false)
            ->withMaxAge(123123)
            ->withValue('a-random-value');

        $dateTime   = new DateTimeImmutable();
        $middleware = new SessionMiddleware(
            Configuration::forSymmetricSigner(
                new Sha256(),
                self::makeRandomSymmetricKey()
            ),
            $defaultCookie,
            123456,
            new FrozenClock($dateTime),
            123
        );

        $response = $middleware->process(new ServerRequest(), $this->writingMiddleware());

        self::assertNull($this->getCookie($response)->getValue());

        $tokenCookie = $this->getCookie($response, 'a-different-cookie-name');

        self::assertNotEmpty($tokenCookie->getValue());
        self::assertNotSame($defaultCookie->getValue(), $tokenCookie->getValue());
        self::assertSame($defaultCookie->getDomain(), $tokenCookie->getDomain());
        self::assertSame($defaultCookie->getPath(), $tokenCookie->getPath());
        self::assertSame($defaultCookie->getHttpOnly(), $tokenCookie->getHttpOnly());
        self::assertSame($defaultCookie->getMaxAge(), $tokenCookie->getMaxAge());
        self::assertEquals($dateTime->getTimestamp() + 123456, $tokenCookie->getExpires());
    }

    public function testSessionTokenParsingIsDelayedWhenSessionIsNotBeingUsed(): void
    {
        $key    = self::makeRandomSymmetricKey();
        $signer = $this->createMock(Signer::class);

        $signer->expects(self::never())->method('verify');
        $signer->method('algorithmId')->willReturn('HS256');

        $currentTimeProvider    = new SystemClock(new DateTimeZone(date_default_timezone_get()));
        $setCookie              = SetCookie::create(SessionMiddleware::DEFAULT_COOKIE);
        $middleware             = new SessionMiddleware(
            Configuration::forSymmetricSigner(
                $signer,
                $key
            ),
            $setCookie,
            100,
            $currentTimeProvider
        );
        $configurationForBuiler = Configuration::forSymmetricSigner(
            new Sha256(),
            $key
        );
        $request                = (new ServerRequest())
            ->withCookieParams([
                SessionMiddleware::DEFAULT_COOKIE => $configurationForBuiler->builder()
                    ->issuedAt(new DateTimeImmutable())
                    ->withClaim(SessionMiddleware::SESSION_CLAIM, DefaultSessionData::fromTokenData(['foo' => 'bar']))
                    ->getToken($configurationForBuiler->signer(), $configurationForBuiler->signingKey())
                    ->toString(),
            ]);

        $middleware->process(
            $request,
            $this->fakeDelegate(static function (ServerRequestInterface $request) {
                self::assertInstanceOf(
                    SessionInterface::class,
                    $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE)
                );

                return new Response();
            })
        );
    }

    public function testShouldRegenerateTokenWhenRequestHasATokenThatIsAboutToExpire(): void
    {
        $dateTime      = new DateTimeImmutable();
        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            self::makeRandomSymmetricKey()
        );
        $middleware    = new SessionMiddleware(
            $configuration,
            SetCookie::create(SessionMiddleware::DEFAULT_COOKIE),
            1000,
            new FrozenClock($dateTime),
            300
        );

        $expiringToken = (new ServerRequest())
            ->withCookieParams([
                SessionMiddleware::DEFAULT_COOKIE => $configuration->builder()
                    ->issuedAt(new DateTimeImmutable('-800 second'))
                    ->canOnlyBeUsedAfter(new DateTimeImmutable('-800 second'))
                    ->expiresAt(new DateTimeImmutable('+200 second'))
                    ->withClaim(SessionMiddleware::SESSION_CLAIM, DefaultSessionData::fromTokenData(['foo' => 'bar']))
                    ->getToken($configuration->signer(), $configuration->signingKey())
                    ->toString(),
            ]);

        $initialResponse = new Response();

        $response = $middleware->process($expiringToken, $this->fakeDelegate(static function () use ($initialResponse) {
            return $initialResponse;
        }));

        self::assertNotSame($initialResponse, $response);

        $tokenCookie = $this->getCookie($response);

        self::assertNotEmpty($tokenCookie->getValue());
        self::assertEquals($dateTime->getTimestamp() + 1000, $tokenCookie->getExpires());
    }

    public function testShouldNotRegenerateTokenWhenRequestHasATokenThatIsFarFromExpiration(): void
    {
        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            self::makeRandomSymmetricKey()
        );
        $middleware    = new SessionMiddleware(
            $configuration,
            SetCookie::create(SessionMiddleware::DEFAULT_COOKIE),
            1000,
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
            300
        );

        $validToken = (new ServerRequest())
            ->withCookieParams([
                SessionMiddleware::DEFAULT_COOKIE => $configuration->builder()
                    ->issuedAt(new DateTimeImmutable('-100 second'))
                    ->canOnlyBeUsedAfter(new DateTimeImmutable('-100 second'))
                    ->expiresAt(new DateTimeImmutable('+900 second'))
                    ->withClaim(SessionMiddleware::SESSION_CLAIM, DefaultSessionData::fromTokenData(['foo' => 'bar']))
                    ->getToken($configuration->signer(), $configuration->signingKey())
                    ->toString(),
            ]);

        $this->ensureSameResponse($middleware, $validToken);
    }

    /**
     * @return array<array<callable(): SessionMiddleware>>
     */
    public function validMiddlewaresProvider(): array
    {
        return $this->defaultMiddlewaresProvider() + [
            'from-constructor' => [
                static function (): SessionMiddleware {
                    return new SessionMiddleware(
                        Configuration::forSymmetricSigner(
                            new Signer\Hmac\Sha512(),
                            self::makeRandomSymmetricKey()
                        ),
                        SetCookie::create(SessionMiddleware::DEFAULT_COOKIE),
                        100,
                        new SystemClock(new DateTimeZone(date_default_timezone_get()))
                    );
                },
            ],
        ];
    }

    /**
     * @return array<array<callable(): SessionMiddleware>>
     */
    public function defaultMiddlewaresProvider(): array
    {
        return [
            'from-symmetric' => [
                static function (): SessionMiddleware {
                    return SessionMiddleware::fromSymmetricKeyDefaults(
                        self::makeRandomSymmetricKey(),
                        100
                    );
                },
            ],
            'from-asymmetric' => [
                static function (): SessionMiddleware {
                    return SessionMiddleware::fromRsaAsymmetricKeyDefaults(
                        Signer\Key\InMemory::file(__DIR__ . '/../../keys/private_key.pem'),
                        Signer\Key\InMemory::file(__DIR__ . '/../../keys/public_key.pem'),
                        200
                    );
                },
            ],
        ];
    }

    public function testMutableCookieWillNotBeUsed(): void
    {
        $cookie = MutableBadCookie::create(SessionMiddleware::DEFAULT_COOKIE);

        assert($cookie instanceof MutableBadCookie);

        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            self::makeRandomSymmetricKey()
        );
        $middleware    = new SessionMiddleware(
            $configuration,
            $cookie,
            1000,
            new SystemClock(new DateTimeZone(date_default_timezone_get()))
        );

        $cookie->mutated = true;

        self::assertStringStartsWith(
            '__Secure-slsession=',
            $middleware
                ->process(new ServerRequest(), $this->writingMiddleware())
                ->getHeaderLine('Set-Cookie')
        );
    }

    private function ensureSameResponse(
        SessionMiddleware $middleware,
        ServerRequestInterface $request,
        ?RequestHandlerInterface $next = null
    ): ResponseInterface {
        $initialResponse = new Response();

        $handleRequest = $this->createMock(RequestHandlerInterface::class);

        if ($next === null) {
            $handleRequest
                ->expects(self::once())
                ->method('handle')
                ->willReturn($initialResponse);
        } else {
            // capturing `$initialResponse` from the `$next` handler
            $handleRequest
                ->expects(self::once())
                ->method('handle')
                ->willReturnCallback(static function (ServerRequestInterface $serverRequest) use ($next, & $initialResponse) {
                    $response = $next->handle($serverRequest);

                    $initialResponse = $response;

                    return $response;
                });
        }

        $response = $middleware->process($request, $handleRequest);

        self::assertSame($initialResponse, $response);

        return $response;
    }

    private function ensureClearsSessionCookie(
        SessionMiddleware $middleware,
        ServerRequestInterface $request,
        RequestHandlerInterface $next
    ): ResponseInterface {
        $response = $middleware->process($request, $next);

        $cookie = $this->getCookie($response);

        self::assertLessThan((new DateTime('-29 day'))->getTimestamp(), $cookie->getExpires());
        self::assertEmpty($cookie->getValue());

        return $response;
    }

    private function createToken(SessionMiddleware $middleware, DateTimeImmutable $issuedAt, DateTimeImmutable $expiration): string
    {
        $config = $this->getJwtConfiguration($middleware);

        return $config->builder()
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($expiration)
            ->withClaim(SessionMiddleware::SESSION_CLAIM, DefaultSessionData::fromTokenData(['foo' => 'bar']))
            ->getToken($config->signer(), $config->signingKey())
            ->toString();
    }

    private function createTokenWithCustomClaim(
        SessionMiddleware $middleware,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiration,
        mixed $claim
    ): string {
        $config = $this->getJwtConfiguration($middleware);

        return $config->builder()
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($expiration)
            ->withClaim(SessionMiddleware::SESSION_CLAIM, $claim)
            ->getToken($config->signer(), $config->signingKey())
            ->toString();
    }

    private function emptyValidationMiddleware(): RequestHandlerInterface
    {
        return $this->fakeDelegate(
            static function (ServerRequestInterface $request) {
                $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

                self::assertInstanceOf(SessionInterface::class, $session);
                self::assertTrue($session->isEmpty());

                return new Response();
            }
        );
    }

    private function writingMiddleware(string $value = 'bar'): RequestHandlerInterface
    {
        return $this->fakeDelegate(
            static function (ServerRequestInterface $request) use ($value) {
                $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                assert($session instanceof SessionInterface);
                $session->set('foo', $value);

                return new Response();
            }
        );
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

    /**
     * @return ServerRequest
     */
    private function requestWithResponseCookies(ResponseInterface $response): ServerRequestInterface
    {
        return (new ServerRequest())->withCookieParams([
            SessionMiddleware::DEFAULT_COOKIE => $this->getCookie($response)->getValue(),
        ]);
    }

    private function getCookie(ResponseInterface $response, string $name = SessionMiddleware::DEFAULT_COOKIE): SetCookie
    {
        return FigResponseCookies::get($response, $name);
    }

    private function getJwtConfiguration(SessionMiddleware $middleware): Configuration
    {
        $property = new ReflectionProperty(SessionMiddleware::class, 'config');

        $property->setAccessible(true);

        $config = $property->getValue($middleware);

        assert($config instanceof Configuration);

        return $config;
    }

    /**
     * @see https://tools.ietf.org/html/rfc6265#section-4.1.2.5 for Secure flag
     * @see https://tools.ietf.org/html/rfc6265#section-4.1.2.6 for HttpOnly flag
     * @see https://github.com/psr7-sessions/storageless/pull/46 for / path
     * @see https://tools.ietf.org/html/draft-ietf-httpbis-cookie-same-site for SameSite flag
     * @see https://tools.ietf.org/html/draft-ietf-httpbis-cookie-prefixes for __Secure- prefix
     */
    private static function assertCookieIsSecure(SetCookie $cookie): void
    {
        self::assertTrue($cookie->getSecure());
        self::assertTrue($cookie->getHttpOnly());
        self::assertSame('/', $cookie->getPath());
        self::assertEquals(SameSite::lax(), $cookie->getSameSite());
        self::assertStringStartsWith('__Secure-', $cookie->getName());
    }

    private static function makeRandomSymmetricKey(): Signer\Key\InMemory
    {
        return Signer\Key\InMemory::plainText(base64_encode(random_bytes(128)));
    }
}
