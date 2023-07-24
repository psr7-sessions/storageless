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

use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use PHPUnit\Framework\TestCase;
use PSR7Sessions\Storageless\Http\SessionMiddlewareConfiguration;

use function random_bytes;

/** @covers \PSR7Sessions\Storageless\Http\SessionMiddlewareConfiguration */
final class SessionMiddlewareConfigurationTest extends TestCase
{
    private Configuration $jwtConfig;

    protected function setUp(): void
    {
        $this->jwtConfig = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText(random_bytes(32)),
        );
    }

    public function testProvideADefaultSystemClock(): void
    {
        $clock = (new SessionMiddlewareConfiguration($this->jwtConfig))->getClock();

        self::assertGreaterThan(0, $clock->now()->getTimestamp());
    }

    /**
     * @see https://tools.ietf.org/html/rfc6265#section-4.1.2.5 for Secure flag
     * @see https://tools.ietf.org/html/rfc6265#section-4.1.2.6 for HttpOnly flag
     * @see https://github.com/psr7-sessions/storageless/pull/46 for / path
     * @see https://tools.ietf.org/html/draft-ietf-httpbis-cookie-same-site for SameSite flag
     * @see https://tools.ietf.org/html/draft-ietf-httpbis-cookie-prefixes for __Secure- prefix
     */
    public function testProvideADefaultSecureCookie(): void
    {
        $cookie = (new SessionMiddlewareConfiguration($this->jwtConfig))->getCookie();

        self::assertTrue($cookie->getSecure());
        self::assertTrue($cookie->getHttpOnly());
        self::assertSame('/', $cookie->getPath());
        self::assertEquals(SameSite::lax(), $cookie->getSameSite());
        self::assertStringStartsWith('__Secure-', $cookie->getName());
    }

    public function testProvideNonEmptyDefaultsForScalarAttributes(): void
    {
        $config = new SessionMiddlewareConfiguration($this->jwtConfig);

        self::assertGreaterThan(0, $config->getIdleTimeout());
        self::assertGreaterThan(0, $config->getRefreshTime());
        self::assertNotEmpty($config->getSessionAttribute());
    }

    public function testImmutability(): void
    {
        $leftConfig = new SessionMiddlewareConfiguration($this->jwtConfig);
        self::assertNotSame($this->jwtConfig, $leftConfig->getJwtConfiguration());

        $jwtConfig   = clone $this->jwtConfig;
        $rightConfig = $leftConfig->withJwtConfiguration($jwtConfig);
        self::assertNotSame($leftConfig, $rightConfig);
        self::assertNotSame($jwtConfig, $rightConfig->getJwtConfiguration());

        $clock      = FrozenClock::fromUTC();
        $leftConfig = $rightConfig->withClock($clock);
        self::assertNotSame($leftConfig, $rightConfig);
        self::assertNotSame($clock, $leftConfig->getClock());

        $cookie      = SetCookie::create('foo');
        $rightConfig = $leftConfig->withCookie($cookie);
        self::assertNotSame($leftConfig, $rightConfig);
        self::assertNotSame($cookie, $rightConfig->getCookie());

        $idleTimeout = $leftConfig->getIdleTimeout() + 1;
        $leftConfig  = $rightConfig->withIdleTimeout($idleTimeout);
        self::assertNotSame($leftConfig, $rightConfig);
        self::assertSame($idleTimeout, $leftConfig->getIdleTimeout());

        $refreshTime = $leftConfig->getRefreshTime() + 1;
        $rightConfig = $leftConfig->withRefreshTime($refreshTime);
        self::assertNotSame($leftConfig, $rightConfig);
        self::assertSame($refreshTime, $rightConfig->getRefreshTime());

        $sessionAttribute = $leftConfig->getSessionAttribute() . 'foo';
        $leftConfig       = $rightConfig->withSessionAttribute($sessionAttribute);
        self::assertNotSame($leftConfig, $rightConfig);
        self::assertSame($sessionAttribute, $leftConfig->getSessionAttribute());
    }
}
