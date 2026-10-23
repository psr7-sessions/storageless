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

use function array_any;
use function array_column;
use function array_filter;
use function array_map;
use function implode;
use function in_array;
use function is_string;
use function preg_match_all;
use function sprintf;
use function strtolower;

use const PREG_SET_ORDER;

/** @psalm-immutable */
final readonly class SessionMiddleware implements MiddlewareInterface
{
    public const string SESSION_CLAIM     = 'session-data';
    public const string SESSION_ATTRIBUTE = 'session';

    private const string CACHE_CONTROL_HEADER = 'Cache-Control';

    /** Directives allowing shared caches to store a response, unless overridden by `private` or `no-store` */
    private const array SHARED_CACHE_DIRECTIVES = ['public', 's-maxage'];

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
        $sessionContainer  = LazySession::fromContainerBuildingCallback(function () use ($token): SessionInterface {
            return $this->extractSessionContainer($token);
        });

        return $this->appendToken(
            $sessionContainer,
            $handler->handle($request->withAttribute($this->config->getSessionAttribute(), $sessionContainer)),
            $token,
            $sameOriginRequest,
        );
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
    private function appendToken(
        SessionInterface $sessionContainer,
        Response $response,
        Token|null $token,
        SameOriginRequest $sameOriginRequest,
    ): Response {
        $sessionContainerChanged = $sessionContainer->hasChanged();

        if ($sessionContainerChanged && $sessionContainer->isEmpty()) {
            return self::withSessionCookie($response, $this->getExpirationCookie());
        }

        if ($sessionContainerChanged || $this->shouldTokenBeRefreshed($token, $response)) {
            try {
                $tokenCookie = $this->getTokenCookie($sessionContainer, $sameOriginRequest);
            } catch (SourceMissing) {
                // a session that cannot be bound to the client fingerprint must not be sent
                return $response;
            }

            return self::withSessionCookie($response, $tokenCookie);
        }

        return $response;
    }

    private function shouldTokenBeRefreshed(Token|null $token, Response $response): bool
    {
        if ($token === null) {
            return false;
        }

        // refreshing is optional: skip it, rather than making private a response meant for shared caches
        if (self::isExplicitlyStorableBySharedCaches(self::cacheControlDirectives($response))) {
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

    /**
     * Adds the session cookie to the response, preventing shared caches (CDNs, reverse proxies, etc.) from storing it:
     * they would serve the session to other clients
     */
    private static function withSessionCookie(Response $response, SetCookie $cookie): Response
    {
        $response   = FigResponseCookies::set($response, $cookie);
        $directives = self::cacheControlDirectives($response);

        if (self::isNotStorableBySharedCaches($directives)) {
            return $response;
        }

        $keptDirectives = array_column(
            array_filter(
                $directives,
                static fn (array $directive): bool => $directive['name'] !== 'private'
                    && ! in_array($directive['name'], self::SHARED_CACHE_DIRECTIVES, true),
            ),
            'directive',
        );

        return $response->withHeader(self::CACHE_CONTROL_HEADER, implode(', ', ['private', ...$keptDirectives]));
    }

    /** @param list<array{name: lowercase-string, directive: string, qualified: bool}> $directives */
    private static function isExplicitlyStorableBySharedCaches(array $directives): bool
    {
        return ! self::isNotStorableBySharedCaches($directives)
            && array_any(
                $directives,
                static fn (array $directive): bool => in_array($directive['name'], self::SHARED_CACHE_DIRECTIVES, true),
            );
    }

    /**
     * A qualified `private="Header-Name"` directive still allows shared caches to store the response
     *
     * @param list<array{name: lowercase-string, directive: string, qualified: bool}> $directives
     */
    private static function isNotStorableBySharedCaches(array $directives): bool
    {
        return array_any(
            $directives,
            static fn (array $directive): bool => $directive['name'] === 'no-store'
                || ($directive['name'] === 'private' && ! $directive['qualified']),
        );
    }

    /** @return list<array{name: lowercase-string, directive: string, qualified: bool}> */
    private static function cacheControlDirectives(Response $response): array
    {
        // directive names, optionally followed by a token or a quoted string, which may contain commas
        preg_match_all(
            '/(?<name>[^\s,="]+)(?<value>\s*=\s*(?:"(?:[^"\\\\]|\\\\.)*"|[^\s,"]*))?/',
            $response->getHeaderLine(self::CACHE_CONTROL_HEADER),
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(
            static fn (array $match): array => [
                'name'      => strtolower($match['name']),
                'directive' => $match[0],
                'qualified' => isset($match['value']),
            ],
            $matches,
        );
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
