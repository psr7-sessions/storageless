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

use BadMethodCallException;
use DateInterval;
use DateTimeZone;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use InvalidArgumentException;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use OutOfBoundsException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSR7Sessions\Storageless\Http\ClientFingerprint\SameOriginRequest;
use PSR7Sessions\Storageless\Http\ClientFingerprint\SourceMissing;
use PSR7Sessions\Storageless\Session\DefaultSessionData;
use PSR7Sessions\Storageless\Session\LazySession;
use PSR7Sessions\Storageless\Session\SessionInterface;
use stdClass;

use function is_string;
use function sprintf;

/** @psalm-immutable */
final readonly class SessionMiddleware implements MiddlewareInterface
{
    public const string SESSION_CLAIM     = 'session-data';
    public const string SESSION_ATTRIBUTE = 'session';

    /** Same date used by `ext/session` */
    private const string EXPIRED_DATE     = 'Thu, 19 Nov 1981 08:52:00 GMT';
    private const string HTTP_DATE_FORMAT = 'D, d M Y H:i:s \G\M\T';

    public function __construct(
        private Configuration $config,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $sameOriginRequest = new SameOriginRequest($this->config->getClientFingerprintConfiguration(), $request);
        $token             = $this->parseToken($request, $sameOriginRequest);
        $sessionLoaded     = false;
        $sessionContainer  = LazySession::fromContainerBuildingCallback(
            function () use ($token, &$sessionLoaded): SessionInterface {
                $sessionLoaded = true;

                return $this->extractSessionContainer($token);
            },
        );

        $response      = $handler->handle($request->withAttribute($this->config->getSessionAttribute(), $sessionContainer));
        $sessionCookie = $this->getSessionCookie($sessionContainer, $token, $sameOriginRequest);

        if ($sessionCookie !== null) {
            $response = FigResponseCookies::set($response, $sessionCookie);
        }

        if (! $sessionLoaded && $sessionCookie === null) {
            return $response;
        }

        return $this->withCacheLimiterHeaders($response);
    }

    /**
     * Extract the token from the given request object
     */
    private function parseToken(Request $request, SameOriginRequest $sameOriginRequest): UnencryptedToken|null
    {
        $cookies    = $request->getCookieParams();
        $cookieName = $this->config->getCookie()->getName();

        if (! isset($cookies[$cookieName])) {
            return null;
        }

        $cookie = $cookies[$cookieName];
        if (! is_string($cookie) || $cookie === '') {
            return null;
        }

        $jwtConfiguration = $this->config->getJwtConfiguration();
        try {
            $token = $jwtConfiguration->parser()->parse($cookie);
        } catch (InvalidArgumentException | CannotDecodeContent) {
            return null;
        }

        /** @infection-ignore-all */
        if (! $token instanceof UnencryptedToken) {
            return null;
        }

        $constraints = [
            new StrictValidAt($this->config->getClock()),
            new SignedWith($jwtConfiguration->signer(), $jwtConfiguration->verificationKey()),
            $sameOriginRequest,
        ];

        if (! $jwtConfiguration->validator()->validate($token, ...$constraints)) {
            return null;
        }

        return $token;
    }

    /** @throws OutOfBoundsException */
    private function extractSessionContainer(UnencryptedToken|null $token): SessionInterface
    {
        if (! $token) {
            return DefaultSessionData::newEmptySession();
        }

        try {
            return DefaultSessionData::fromDecodedTokenData(
                (object) $token->claims()->get(self::SESSION_CLAIM, new stdClass()),
            );
        } catch (BadMethodCallException) {
            return DefaultSessionData::newEmptySession();
        }
    }

    /**
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    private function getSessionCookie(
        SessionInterface $sessionContainer,
        Token|null $token,
        SameOriginRequest $sameOriginRequest,
    ): SetCookie|null {
        $sessionContainerChanged = $sessionContainer->hasChanged();

        if ($sessionContainerChanged && $sessionContainer->isEmpty()) {
            return $this->getExpirationCookie();
        }

        if (! $sessionContainerChanged && ! $this->shouldTokenBeRefreshed($token)) {
            return null;
        }

        try {
            return $this->getTokenCookie($sessionContainer, $sameOriginRequest);
        } catch (SourceMissing) {
            // a session that cannot be bound to the client fingerprint must not be sent
            return null;
        }
    }

    /**
     * Prevents caching of responses depending on the session, as `session.cache_limiter` does in `ext/session`
     */
    private function withCacheLimiterHeaders(Response $response): Response
    {
        foreach ($this->getCacheLimiterHeaders() as $name => $value) {
            // as with `ext/session`, headers set by the application take precedence
            if ($response->hasHeader($name)) {
                continue;
            }

            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /** @return array<non-empty-string, non-empty-string> */
    private function getCacheLimiterHeaders(): array
    {
        $cacheExpire = $this->config->getCacheExpire();

        return match ($this->config->getCacheLimiter()) {
            CacheLimiter::NoCache => [
                'Expires'       => self::EXPIRED_DATE,
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma'        => 'no-cache',
            ],
            CacheLimiter::Private => [
                'Expires'       => self::EXPIRED_DATE,
                'Cache-Control' => sprintf('private, max-age=%d', $cacheExpire),
            ],
            CacheLimiter::Public => [
                'Expires'       => $this->config->getClock()
                    ->now()
                    ->add(new DateInterval(sprintf('PT%sS', $cacheExpire)))
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format(self::HTTP_DATE_FORMAT),
                'Cache-Control' => sprintf('public, max-age=%d', $cacheExpire),
            ],
            CacheLimiter::None => [],
        };
    }

    private function shouldTokenBeRefreshed(Token|null $token): bool
    {
        if ($token === null) {
            return false;
        }

        return $token->hasBeenIssuedBefore(
            $this->config->getClock()
                ->now()
                ->sub(new DateInterval(sprintf('PT%sS', $this->config->getRefreshTime()))),
        );
    }

    /**
     * @throws BadMethodCallException
     * @throws SourceMissing
     */
    private function getTokenCookie(SessionInterface $sessionContainer, SameOriginRequest $sameOriginRequest): SetCookie
    {
        $now       = $this->config->getClock()->now();
        $expiresAt = $now->add(new DateInterval(sprintf('PT%sS', $this->config->getIdleTimeout())));

        $jwtConfiguration = $this->config->getJwtConfiguration();

        $builder = $jwtConfiguration->builder(ChainedFormatter::withUnixTimestampDates())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($expiresAt)
            ->withClaim(self::SESSION_CLAIM, $sessionContainer);

        $builder = $sameOriginRequest->configure($builder);

        return $this
            ->config->getCookie()
            ->withValue(
                $builder
                    ->getToken($jwtConfiguration->signer(), $jwtConfiguration->signingKey())
                    ->toString(),
            )
            ->withExpires($expiresAt);
    }

    private function getExpirationCookie(): SetCookie
    {
        return $this
            ->config->getCookie()
            ->withValue(null)
            ->withExpires(
                $this->config->getClock()
                    ->now()
                    ->modify('-30 days'),
            );
    }
}
