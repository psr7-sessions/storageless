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
use PSR7Sessions\Storageless\Http\Config;
use SplObjectStorage;

use function random_bytes;

/** @covers \PSR7Sessions\Storageless\Http\Config */
final class ConfigTest extends TestCase
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
        $clock = (new Config($this->jwtConfig))->getClock();

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
        $cookie = (new Config($this->jwtConfig))->getCookie();

        self::assertTrue($cookie->getSecure());
        self::assertTrue($cookie->getHttpOnly());
        self::assertSame('/', $cookie->getPath());
        self::assertEquals(SameSite::lax(), $cookie->getSameSite());
        self::assertStringStartsWith('__Secure-', $cookie->getName());
    }

    public function testProvideNonEmptyDefaultsForScalarAttributes(): void
    {
        $config = new Config($this->jwtConfig);

        self::assertGreaterThan(0, $config->getIdleTimeout());
        self::assertGreaterThan(0, $config->getRefreshTime());
        self::assertNotEmpty($config->getSessionAttribute());
    }

    public function testImmutability(): void
    {
        $map = new SplObjectStorage();

        $config       = new Config($this->jwtConfig);
        $map[$config] = true;
        self::assertNotSame($this->jwtConfig, $config->getJwtConfiguration());

        $jwtConfig    = clone $this->jwtConfig;
        $config       = $config->withJwtConfiguration($jwtConfig);
        $map[$config] = true;
        self::assertNotSame($jwtConfig, $config->getJwtConfiguration());

        $clock        = FrozenClock::fromUTC();
        $config       = $config->withClock($clock);
        $map[$config] = true;
        self::assertNotSame($clock, $config->getClock());

        $cookie       = SetCookie::create('foo');
        $config       = $config->withCookie($cookie);
        $map[$config] = true;
        self::assertNotSame($cookie, $config->getCookie());

        $idleTimeout  = $config->getIdleTimeout() + 1;
        $config       = $config->withIdleTimeout($idleTimeout);
        $map[$config] = true;
        self::assertSame($idleTimeout, $config->getIdleTimeout());

        $refreshTime  = $config->getRefreshTime() + 1;
        $config       = $config->withRefreshTime($refreshTime);
        $map[$config] = true;
        self::assertSame($refreshTime, $config->getRefreshTime());

        $sessionAttribute = $config->getSessionAttribute() . 'foo';
        $config           = $config->withSessionAttribute($sessionAttribute);
        $map[$config]     = true;
        self::assertSame($sessionAttribute, $config->getSessionAttribute());

        self::assertCount(7, $map);
    }
}
