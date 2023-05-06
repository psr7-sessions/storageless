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

namespace PSR7SessionsTest\Storageless\Session;

use Closure;
use DateTimeImmutable;
use Dflydev\FigCookies\Cookie;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Generator;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Signer;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Service\SessionStorage;
use PSR7Sessions\Storageless\Service\StoragelessSession;
use Throwable;

use function base64_encode;
use function random_bytes;

/** @covers \PSR7Sessions\Storageless\Service\StoragelessSession */
final class StoragelessSessionTest extends TestCase
{
    /** @return Generator<int, array{0: Closure(): SessionStorage, 1: ServerRequestInterface|ResponseInterface, 2: string}> */
    public function itThrowsExceptionWhenCookieTypeIsInvalidProvider(): Generator
    {
        yield [
            static fn (): SessionStorage => StoragelessSession::fromSymmetricKeyDefaults(
                self::makeRandomSymmetricKey(),
                0,
                SetCookie::create(SessionMiddleware::DEFAULT_COOKIE),
                new FrozenClock(new DateTimeImmutable('2010-05-15 16:00:00')),
            ),
            new ServerRequest(),
            'The default cookie is not a Cookie type.',
        ];

        yield [
            static fn (): SessionStorage => StoragelessSession::fromRsaAsymmetricKeyDefaults(
                Signer\Key\InMemory::file(__DIR__ . '/../../keys/private_key.pem'),
                Signer\Key\InMemory::file(__DIR__ . '/../../keys/public_key.pem'),
                0,
                Cookie::create(SessionMiddleware::DEFAULT_COOKIE),
                new FrozenClock(new DateTimeImmutable('2010-05-15 16:00:00')),
            ),
            new Response(),
            'The default cookie is not a SetCookie type.',
        ];
    }

    /**
     * @param Closure(): SessionStorage $sessionStorageClosure
     *
     * @dataProvider itThrowsExceptionWhenCookieTypeIsInvalidProvider
     */
    public function testItThrowsExceptionWhenCookieTypeIsInvalid(
        Closure $sessionStorageClosure,
        ServerRequestInterface|ResponseInterface $message,
        string $exceptionMessage,
    ): void {
        $sessionStorage = $sessionStorageClosure();

        $session = $sessionStorage->get($message);
        $session->set('foo', 'bar');

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage($exceptionMessage);

        $sessionStorage->withSession($message, $session);
    }

    /** @return Generator<int, array{0: Closure(): SessionStorage, 1: SetCookie}> */
    public function itCanCustomizeACookieProvider(): Generator
    {
        yield [
            static fn (): SessionStorage => StoragelessSession::fromSymmetricKeyDefaults(
                self::makeRandomSymmetricKey(),
            ),
            SessionMiddleware::buildDefaultCookie(),
        ];

        yield [
            static fn (): SessionStorage => StoragelessSession::fromRsaAsymmetricKeyDefaults(
                Signer\Key\InMemory::file(__DIR__ . '/../../keys/private_key.pem'),
                Signer\Key\InMemory::file(__DIR__ . '/../../keys/public_key.pem'),
            ),
            SessionMiddleware::buildDefaultCookie(),
        ];

        yield [
            static fn (): SessionStorage => StoragelessSession::fromSymmetricKeyDefaults(
                self::makeRandomSymmetricKey(),
                100,
                SessionMiddleware::buildDefaultCookie()->withPath('/foo'),
            ),
            SessionMiddleware::buildDefaultCookie()->withPath('/foo'),
        ];

        yield [
            static fn (): SessionStorage => StoragelessSession::fromRsaAsymmetricKeyDefaults(
                Signer\Key\InMemory::file(__DIR__ . '/../../keys/private_key.pem'),
                Signer\Key\InMemory::file(__DIR__ . '/../../keys/public_key.pem'),
                100,
                SessionMiddleware::buildDefaultCookie()->withPath('/foo'),
            ),
            SessionMiddleware::buildDefaultCookie()->withPath('/foo'),
        ];
    }

    /** @return Generator<int, array{0: Closure(): SessionStorage, 1: ClockInterface}> */
    public function itCanCustomizeAClockProvider(): Generator
    {
        yield [
            static fn (): SessionStorage => StoragelessSession::fromSymmetricKeyDefaults(
                self::makeRandomSymmetricKey(),
                0,
            ),
            SystemClock::fromUTC(),
        ];

        yield [
            static fn (): SessionStorage => StoragelessSession::fromRsaAsymmetricKeyDefaults(
                Signer\Key\InMemory::file(__DIR__ . '/../../keys/private_key.pem'),
                Signer\Key\InMemory::file(__DIR__ . '/../../keys/public_key.pem'),
                0,
            ),
            SystemClock::fromUTC(),
        ];

        yield [
            static fn (): SessionStorage => StoragelessSession::fromSymmetricKeyDefaults(
                self::makeRandomSymmetricKey(),
                0,
                null,
                new FrozenClock(new DateTimeImmutable('2010-05-15 16:00:00')),
            ),
            new FrozenClock(new DateTimeImmutable('2010-05-15 16:00:00')),
        ];

        yield [
            static fn (): SessionStorage => StoragelessSession::fromRsaAsymmetricKeyDefaults(
                Signer\Key\InMemory::file(__DIR__ . '/../../keys/private_key.pem'),
                Signer\Key\InMemory::file(__DIR__ . '/../../keys/public_key.pem'),
                0,
                null,
                new FrozenClock(new DateTimeImmutable('2010-05-15 16:00:00')),
            ),
            new FrozenClock(new DateTimeImmutable('2010-05-15 16:00:00')),
        ];
    }

    /**
     * @param Closure(): SessionStorage $sessionStorageClosure
     *
     * @dataProvider itCanCustomizeACookieProvider
     */
    public function testItCanCustomizeACookie(Closure $sessionStorageClosure, SetCookie|null $cookie): void
    {
        $sessionStorage = $sessionStorageClosure();

        $response = new Response();

        $session = $sessionStorage->get($response);
        $session->set('foo', 'bar');

        $response = $sessionStorage->withSession($response, $session);

        $cookie ??= SessionMiddleware::buildDefaultCookie();

        $this->assertEquals(
            $cookie->getPath(),
            $this->getCookie($response)->getPath(),
        );

        $this->assertEquals(
            $cookie->getHttpOnly(),
            $this->getCookie($response)->getHttpOnly(),
        );

        $this->assertEquals(
            $cookie->getSecure(),
            $this->getCookie($response)->getSecure(),
        );
    }

    /** @return Generator<int, array{0: Closure(): SessionStorage}> */
    public function itCanCreateACookieWhenItIsNotSetProvider(): Generator
    {
        yield [
            static fn (): SessionStorage => StoragelessSession::fromSymmetricKeyDefaults(
                self::makeRandomSymmetricKey(),
            ),
        ];

        yield [
            static fn (): SessionStorage => StoragelessSession::fromRsaAsymmetricKeyDefaults(
                Signer\Key\InMemory::file(__DIR__ . '/../../keys/private_key.pem'),
                Signer\Key\InMemory::file(__DIR__ . '/../../keys/public_key.pem'),
            ),
        ];
    }

    /**
     * @param Closure(): SessionStorage $sessionStorageClosure
     *
     * @dataProvider itCanCreateACookieWhenItIsNotSetProvider
     */
    public function testItCanCreateACookieWhenItIsNotSet(Closure $sessionStorageClosure): void
    {
        $sessionStorage = $sessionStorageClosure();

        $response = new Response();

        $session = $sessionStorage->get($response);
        $session->set('foo', 'bar');

        $response = $sessionStorage->withSession($response, $session);

        $this->assertTrue(
            $this->getCookie($response)->getSecure(),
        );

        $this->assertTrue(
            $this->getCookie($response)->getHttpOnly(),
        );

        $this->assertEquals(
            SameSite::lax(),
            $this->getCookie($response)->getSameSite(),
        );

        $this->assertEquals(
            '/',
            $this->getCookie($response)->getPath(),
        );
    }

    /**
     * @param Closure(): SessionStorage $sessionStorageClosure
     *
     * @dataProvider itCanCustomizeAClockProvider
     */
    public function testItCanUseACustomClock(Closure $sessionStorageClosure, ClockInterface $clock): void
    {
        $sessionStorage = $sessionStorageClosure();

        $response = new Response();
        $session  = $sessionStorage->get($response);
        $session->set('foo', 'bar');

        $cookie = $this->getCookie($sessionStorage->withSession($response, $session));

        $this->assertEquals($clock->now()->getTimestamp(), $cookie->getExpires());
    }

    /** @return Generator<int, array{0: SetCookie}> */
    public function itCanDetectNullOrEmptyJWTProvider(): Generator
    {
        yield [SessionMiddleware::buildDefaultCookie()->withValue('')];

        yield [SessionMiddleware::buildDefaultCookie()->withValue(null)];
    }

    /** @dataProvider itCanDetectNullOrEmptyJWTProvider */
    public function testItCanDetectNullOrEmptyJWT(SetCookie $cookie): void
    {
        $sessionStorage = StoragelessSession::fromSymmetricKeyDefaults(
            self::makeRandomSymmetricKey(),
        );

        $this->assertNull($sessionStorage->cookieToToken($cookie));
    }

    private function getCookie(ResponseInterface $response, string $name = SessionMiddleware::DEFAULT_COOKIE): SetCookie
    {
        return FigResponseCookies::get($response, $name);
    }

    private static function makeRandomSymmetricKey(): Signer\Key\InMemory
    {
        return Signer\Key\InMemory::plainText('test-key_' . base64_encode(random_bytes(128)));
    }
}
