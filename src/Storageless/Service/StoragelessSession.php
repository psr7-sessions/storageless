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

namespace PSR7Sessions\Storageless\Service;

use DateInterval;
use DateTimeImmutable;
use Dflydev\FigCookies\Cookie;
use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\SetCookies;
use Exception;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PSR7Sessions\Storageless\Session\DefaultSessionData;
use PSR7Sessions\Storageless\Session\SessionInterface;
use stdClass;
use Throwable;

use function array_key_exists;
use function assert;
use function is_string;
use function sprintf;

final class StoragelessSession implements SessionStorage
{
    private const DEFAULT_COOKIE       = '__Secure-slsession';
    private const SESSION_CLAIM        = 'session-data';
    private const DEFAULT_IDLE_TIMEOUT = 300;

    private readonly Cookie|SetCookie $cookie;

    public function __construct(
        private readonly Configuration $configuration,
        private readonly int $idleTimeout,
        Cookie|SetCookie $cookie,
        private readonly ClockInterface $clock,
    ) {
        $this->cookie = clone $cookie;
    }

    public static function fromSymmetricKeyDefaults(
        Signer\Key $symmetricKey,
        int $idleTimeout = self::DEFAULT_IDLE_TIMEOUT,
        Cookie|SetCookie|null $cookie = null,
        ClockInterface|null $clock = null,
    ): self {
        return new self(
            Configuration::forSymmetricSigner(
                new Signer\Hmac\Sha256(),
                $symmetricKey,
            ),
            $idleTimeout,
            $cookie ?? SetCookie::create(self::DEFAULT_COOKIE)
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite(SameSite::lax())
                ->withPath('/'),
            $clock ?? SystemClock::fromUTC(),
        );
    }

    public static function fromRsaAsymmetricKeyDefaults(
        Signer\Key $privateRsaKey,
        Signer\Key $publicRsaKey,
        int $idleTimeout = self::DEFAULT_IDLE_TIMEOUT,
        Cookie|SetCookie|null $cookie = null,
        ClockInterface|null $clock = null,
    ): self {
        return new self(
            Configuration::forAsymmetricSigner(
                new Signer\Rsa\Sha256(),
                $privateRsaKey,
                $publicRsaKey,
            ),
            $idleTimeout,
            $cookie ?? SetCookie::create(self::DEFAULT_COOKIE)
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite(SameSite::lax())
                ->withPath('/'),
            $clock ?? SystemClock::fromUTC(),
        );
    }

    public function withSession(ServerRequestInterface|ResponseInterface $message, SessionInterface $session): RequestInterface|ResponseInterface
    {
        if ($message instanceof ResponseInterface) {
            return $this->withResponseSession($message, $session, $this->clock->now());
        }

        return $this->withRequestSession($message, $session, $this->clock->now());
    }

    public function get(ServerRequestInterface|ResponseInterface $message): SessionInterface
    {
        $cookie = $this->getCookieFromMessage($message);

        return $cookie === null
            ? DefaultSessionData::newEmptySession()
            : $this->cookieToSession($cookie);
    }

    public function getCookieFromMessage(ServerRequestInterface|ResponseInterface $message): SetCookie|Cookie|null
    {
        // TODO: Why we cannot use Cookies::fromRequest() ?
        // See: https://github.com/dflydev/dflydev-fig-cookies/issues/57
        if ($message instanceof ServerRequestInterface) {
            $cookies = $message->getCookieParams();

            if (! array_key_exists($this->cookie->getName(), $cookies)) {
                return null;
            }

            $cookieValue = $cookies[$this->cookie->getName()];
            assert(is_string($cookieValue));

            return Cookie::create($this->cookie->getName(), $cookieValue === '' ? null : $cookieValue);
        }

        return SetCookies::fromResponse($message)->get($this->cookie->getName());
    }

    public function cookieToToken(SetCookie|Cookie|null $cookie): UnencryptedToken|null
    {
        if ($cookie === null) {
            return null;
        }

        $jwt = $cookie->getValue();

        if ($jwt === null) {
            return null;
        }

        if ($jwt === '') {
            return null;
        }

        try {
            $token = $this->configuration->parser()->parse($jwt);
        } catch (Throwable) {
            return null;
        }

        if (! $token instanceof UnencryptedToken) {
            return null;
        }

        $isValid = $this
            ->configuration
            ->validator()
            ->validate(
                $token,
                new StrictValidAt($this->clock),
                new SignedWith($this->configuration->signer(), $this->configuration->verificationKey()),
            );

        if ($isValid === false) {
            return null;
        }

        return $token;
    }

    private function withRequestSession(RequestInterface $request, SessionInterface $session, DateTimeImmutable $now): RequestInterface
    {
        if ($session->hasChanged() === false) {
            return $request;
        }

        if (! $this->cookie instanceof Cookie) {
            throw new Exception(
                'The default cookie is not a Cookie type.',
            );
        }

        return FigRequestCookies::set(
            $request,
            $this->appendCookieSession($this->cookie, $session, $now),
        );
    }

    private function withResponseSession(ResponseInterface $response, SessionInterface $session, DateTimeImmutable $now): ResponseInterface
    {
        if (! $this->cookie instanceof SetCookie) {
            throw new Exception(
                'The default cookie is not a SetCookie type.',
            );
        }

        if ($session->isEmpty()) {
            return FigResponseCookies::set(
                $response,
                $this->cookie->withExpires($now->modify('-30 days')),
            );
        }

        return FigResponseCookies::set(
            $response,
            $this
                ->appendCookieSession(
                    $this->cookie->withExpires($now->add(new DateInterval(sprintf('PT%sS', $this->idleTimeout)))),
                    $session,
                    $now,
                ),
        );
    }

    /** @psalm-return ($cookie is SetCookie ? SetCookie : Cookie) */
    private function appendCookieSession(SetCookie|Cookie $cookie, SessionInterface $session, DateTimeImmutable $now): SetCookie|Cookie
    {
        $value = $session->isEmpty()
            ? null
            : $this->configuration->builder(ChainedFormatter::withUnixTimestampDates())
                ->issuedAt($now)
                ->canOnlyBeUsedAfter($now)
                ->expiresAt($now->add(new DateInterval(sprintf('PT%sS', $this->idleTimeout))))
                ->withClaim(self::SESSION_CLAIM, $session)
                ->getToken($this->configuration->signer(), $this->configuration->signingKey())
                ->toString();

        return $cookie->withValue($value);
    }

    private function cookieToSession(SetCookie|Cookie $cookie): SessionInterface
    {
        $token = $this->cookieToToken($cookie);

        if ($token === null) {
            return DefaultSessionData::newEmptySession();
        }

        return DefaultSessionData::fromDecodedTokenData(
            (object) $token->claims()->get(self::SESSION_CLAIM, new stdClass()),
        );
    }
}
