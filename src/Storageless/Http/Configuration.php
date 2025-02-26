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

namespace PSR7Sessions\Storageless\Http;

use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Lcobucci\Clock\Clock;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration as JwtConfig;
use PSR7Sessions\Storageless\Http\ClientFingerprint\Configuration as FingerprintConfig;

/** @psalm-immutable */
final readonly class Configuration
{
    public const int DEFAULT_IDLE_TIMEOUT   = 43200;
    public const int DEFAULT_REFRESH_TIME   = 60;
    public const string DEFAULT_COOKIE_NAME = '__Secure-slsession';

    private JwtConfig $jwtConfiguration;
    private SetCookie $cookie;
    private FingerprintConfig $clientFingerprintConfiguration;

    /**
     * @param positive-int   $idleTimeout
     * @param positive-int   $refreshTime
     * @param literal-string $sessionAttribute
     */
    private function __construct(
        JwtConfig $jwtConfiguration,
        private Clock $clock,
        SetCookie $cookie,
        private int $idleTimeout,
        private int $refreshTime,
        private string $sessionAttribute,
        FingerprintConfig $clientFingerprintConfiguration,
    ) {
        $this->jwtConfiguration               = clone $jwtConfiguration;
        $this->cookie                         = clone $cookie;
        $this->clientFingerprintConfiguration = clone $clientFingerprintConfiguration;
    }

    public static function fromJwtConfiguration(JwtConfig $jwtConfiguration): self
    {
        return new self(
            $jwtConfiguration,
            SystemClock::fromSystemTimezone(),
            SetCookie::create(self::DEFAULT_COOKIE_NAME)
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite(SameSite::lax())
                ->withPath('/'),
            self::DEFAULT_IDLE_TIMEOUT,
            self::DEFAULT_REFRESH_TIME,
            SessionMiddleware::SESSION_ATTRIBUTE,
            FingerprintConfig::disabled(),
        );
    }

    public function getJwtConfiguration(): JwtConfig
    {
        return $this->jwtConfiguration;
    }

    public function getClock(): Clock
    {
        return $this->clock;
    }

    public function getCookie(): SetCookie
    {
        return $this->cookie;
    }

    /** @return positive-int */
    public function getIdleTimeout(): int
    {
        return $this->idleTimeout;
    }

    /** @return positive-int */
    public function getRefreshTime(): int
    {
        return $this->refreshTime;
    }

    /** @return literal-string */
    public function getSessionAttribute(): string
    {
        return $this->sessionAttribute;
    }

    public function getClientFingerprintConfiguration(): FingerprintConfig
    {
        return $this->clientFingerprintConfiguration;
    }

    public function withJwtConfiguration(JwtConfig $jwtConfiguration): self
    {
        return new self(
            $jwtConfiguration,
            $this->clock,
            $this->cookie,
            $this->idleTimeout,
            $this->refreshTime,
            $this->sessionAttribute,
            $this->clientFingerprintConfiguration,
        );
    }

    public function withClock(Clock $clock): self
    {
        return new self(
            $this->jwtConfiguration,
            $clock,
            $this->cookie,
            $this->idleTimeout,
            $this->refreshTime,
            $this->sessionAttribute,
            $this->clientFingerprintConfiguration,
        );
    }

    public function withCookie(SetCookie $cookie): self
    {
        return new self(
            $this->jwtConfiguration,
            $this->clock,
            $cookie,
            $this->idleTimeout,
            $this->refreshTime,
            $this->sessionAttribute,
            $this->clientFingerprintConfiguration,
        );
    }

    /** @param positive-int $idleTimeout */
    public function withIdleTimeout(int $idleTimeout): self
    {
        return new self(
            $this->jwtConfiguration,
            $this->clock,
            $this->cookie,
            $idleTimeout,
            $this->refreshTime,
            $this->sessionAttribute,
            $this->clientFingerprintConfiguration,
        );
    }

    /** @param positive-int $refreshTime */
    public function withRefreshTime(int $refreshTime): self
    {
        return new self(
            $this->jwtConfiguration,
            $this->clock,
            $this->cookie,
            $this->idleTimeout,
            $refreshTime,
            $this->sessionAttribute,
            $this->clientFingerprintConfiguration,
        );
    }

    /** @param literal-string $sessionAttribute */
    public function withSessionAttribute(string $sessionAttribute): self
    {
        return new self(
            $this->jwtConfiguration,
            $this->clock,
            $this->cookie,
            $this->idleTimeout,
            $this->refreshTime,
            $sessionAttribute,
            $this->clientFingerprintConfiguration,
        );
    }

    public function withClientFingerprintConfiguration(FingerprintConfig $clientFingerprintConfiguration): self
    {
        return new self(
            $this->jwtConfiguration,
            $this->clock,
            $this->cookie,
            $this->idleTimeout,
            $this->refreshTime,
            $this->sessionAttribute,
            $clientFingerprintConfiguration,
        );
    }
}
