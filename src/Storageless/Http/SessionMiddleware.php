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
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use InvalidArgumentException;
use Lcobucci\Clock\Clock;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use OutOfBoundsException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSR7Sessions\Storageless\Session\DefaultSessionData;
use PSR7Sessions\Storageless\Session\LazySession;
use PSR7Sessions\Storageless\Session\SessionInterface;
use stdClass;

use function date_default_timezone_get;
use function sprintf;

final class SessionMiddleware implements MiddlewareInterface
{
    public const SESSION_CLAIM        = 'session-data';
    public const SESSION_ATTRIBUTE    = 'session';
    public const DEFAULT_COOKIE       = '__Secure-slsession';
    public const DEFAULT_REFRESH_TIME = 60;
    private Configuration $config;
    private SetCookie $defaultCookie;

    /** @param non-empty-string $sessionAttribute */
    public function __construct(
        Configuration $configuration,
        SetCookie $defaultCookie,
        private int $idleTimeout,
        private Clock $clock,
        private int $refreshTime = self::DEFAULT_REFRESH_TIME,
        private string $sessionAttribute = self::SESSION_ATTRIBUTE,
    ) {
        $this->config        = $configuration;
        $this->defaultCookie = clone $defaultCookie;
    }

    /**
     * This constructor simplifies instantiation when using HTTPS (REQUIRED!) and symmetric key encryption
     */
    public static function fromSymmetricKeyDefaults(Signer\Key $symmetricKey, int $idleTimeout): self
    {
        return new self(
            Configuration::forSymmetricSigner(
                new Signer\Hmac\Sha256(),
                $symmetricKey,
            ),
            self::buildDefaultCookie(),
            $idleTimeout,
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }

    /**
     * This constructor simplifies instantiation when using HTTPS (REQUIRED!) and asymmetric key encryption
     * based on RSA keys
     */
    public static function fromRsaAsymmetricKeyDefaults(
        Signer\Key $privateRsaKey,
        Signer\Key $publicRsaKey,
        int $idleTimeout,
    ): self {
        return new self(
            Configuration::forAsymmetricSigner(
                new Signer\Rsa\Sha256(),
                $privateRsaKey,
                $publicRsaKey,
            ),
            self::buildDefaultCookie(),
            $idleTimeout,
            new SystemClock(new DateTimeZone(date_default_timezone_get())),
        );
    }

    public static function buildDefaultCookie(): SetCookie
    {
        return SetCookie::create(self::DEFAULT_COOKIE)
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite(SameSite::lax())
            ->withPath('/');
    }

    /**
     * {@inheritdoc}
     *
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $token            = $this->parseToken($request);
        $sessionContainer = LazySession::fromContainerBuildingCallback(function () use ($token): SessionInterface {
            return $this->extractSessionContainer($token);
        });

        return $this->appendToken(
            $sessionContainer,
            $handler->handle($request->withAttribute($this->sessionAttribute, $sessionContainer)),
            $token,
        );
    }

    /**
     * Extract the token from the given request object
     */
    private function parseToken(Request $request): UnencryptedToken|null
    {
        /** @var array<string, string> $cookies */
        $cookies    = $request->getCookieParams();
        $cookieName = $this->defaultCookie->getName();

        if (! isset($cookies[$cookieName])) {
            return null;
        }

        $cookie = $cookies[$cookieName];
        if ($cookie === '') {
            return null;
        }

        try {
            $token = $this->config->parser()->parse($cookie);
        } catch (InvalidArgumentException) {
            return null;
        }

        if (! $token instanceof UnencryptedToken) {
            return null;
        }

        $constraints = [
            new StrictValidAt($this->clock),
            new SignedWith($this->config->signer(), $this->config->verificationKey()),
        ];

        if (! $this->config->validator()->validate($token, ...$constraints)) {
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
    private function appendToken(SessionInterface $sessionContainer, Response $response, Token|null $token): Response
    {
        $sessionContainerChanged = $sessionContainer->hasChanged();

        if ($sessionContainerChanged && $sessionContainer->isEmpty()) {
            return FigResponseCookies::set($response, $this->getExpirationCookie());
        }

        if ($sessionContainerChanged || $this->shouldTokenBeRefreshed($token)) {
            return FigResponseCookies::set($response, $this->getTokenCookie($sessionContainer));
        }

        return $response;
    }

    private function shouldTokenBeRefreshed(Token|null $token): bool
    {
        if ($token === null) {
            return false;
        }

        return $token->hasBeenIssuedBefore(
            $this->clock
                ->now()
                ->sub(new DateInterval(sprintf('PT%sS', $this->refreshTime))),
        );
    }

    /** @throws BadMethodCallException */
    private function getTokenCookie(SessionInterface $sessionContainer): SetCookie
    {
        $now       = $this->clock->now();
        $expiresAt = $now->add(new DateInterval(sprintf('PT%sS', $this->idleTimeout)));

        return $this
            ->defaultCookie
            ->withValue(
                $this->config->builder(ChainedFormatter::withUnixTimestampDates())
                    ->issuedAt($now)
                    ->canOnlyBeUsedAfter($now)
                    ->expiresAt($expiresAt)
                    ->withClaim(self::SESSION_CLAIM, $sessionContainer)
                    ->getToken($this->config->signer(), $this->config->signingKey())
                    ->toString(),
            )
            ->withExpires($expiresAt);
    }

    private function getExpirationCookie(): SetCookie
    {
        return $this
            ->defaultCookie
            ->withValue(null)
            ->withExpires(
                $this->clock
                    ->now()
                    ->modify('-30 days'),
            );
    }
}
