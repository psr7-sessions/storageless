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
use DateTimeImmutable;
use DateTimeZone;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use InvalidArgumentException;
use Lcobucci\Clock\Clock;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\ValidAt;
use Lcobucci\JWT\Validation\Validator;
use OutOfBoundsException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSR7Sessions\Storageless\Session\DefaultSessionData;
use PSR7Sessions\Storageless\Session\LazySession;
use PSR7Sessions\Storageless\Session\SessionInterface;
use stdClass;

use function assert;
use function date_default_timezone_get;
use function sprintf;

final class SessionMiddleware implements MiddlewareInterface
{
    public const ISSUED_AT_CLAIM      = 'iat';
    public const SESSION_CLAIM        = 'session-data';
    public const SESSION_ATTRIBUTE    = 'session';
    public const DEFAULT_COOKIE       = '__Secure-slsession';
    public const DEFAULT_REFRESH_TIME = 60;

    private Signer $signer;

    private Signer\Key\InMemory $signatureKey;

    private Signer\Key\InMemory $verificationKey;

    private int $expirationTime;

    private int $refreshTime;

    private Parser $tokenParser;

    private SetCookie $defaultCookie;

    private Clock $clock;

    public function __construct(
        Signer $signer,
        string $signatureKey,
        string $verificationKey,
        SetCookie $defaultCookie,
        Parser $tokenParser,
        int $expirationTime,
        Clock $clock,
        int $refreshTime = self::DEFAULT_REFRESH_TIME
    ) {
        $this->signer          = $signer;
        $this->signatureKey    = Signer\Key\InMemory::plainText($signatureKey);
        $this->verificationKey = Signer\Key\InMemory::plainText($verificationKey);
        $this->tokenParser     = $tokenParser;
        $this->defaultCookie   = clone $defaultCookie;
        $this->expirationTime  = $expirationTime;
        $this->clock           = $clock;
        $this->refreshTime     = $refreshTime;
    }

    /**
     * This constructor simplifies instantiation when using HTTPS (REQUIRED!) and symmetric key encryption
     */
    public static function fromSymmetricKeyDefaults(string $symmetricKey, int $expirationTime): self
    {
        return new self(
            new Signer\Hmac\Sha256(),
            $symmetricKey,
            $symmetricKey,
            SetCookie::create(self::DEFAULT_COOKIE)
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite(SameSite::lax())
                ->withPath('/'),
            new Parser(),
            $expirationTime,
            new SystemClock(new DateTimeZone(date_default_timezone_get()))
        );
    }

    /**
     * This constructor simplifies instantiation when using HTTPS (REQUIRED!) and asymmetric key encryption
     * based on RSA keys
     */
    public static function fromAsymmetricKeyDefaults(
        string $privateRsaKey,
        string $publicRsaKey,
        int $expirationTime
    ): self {
        return new self(
            new Signer\Rsa\Sha256(),
            $privateRsaKey,
            $publicRsaKey,
            SetCookie::create(self::DEFAULT_COOKIE)
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite(SameSite::lax())
                ->withPath('/'),
            new Parser(),
            $expirationTime,
            new SystemClock(new DateTimeZone(date_default_timezone_get()))
        );
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
            $handler->handle($request->withAttribute(self::SESSION_ATTRIBUTE, $sessionContainer)),
            $token
        );
    }

    /**
     * Extract the token from the given request object
     */
    private function parseToken(Request $request): ?Token
    {
        /** @var array<string, string> $cookies */
        $cookies    = $request->getCookieParams();
        $cookieName = $this->defaultCookie->getName();

        if (! isset($cookies[$cookieName])) {
            return null;
        }

        try {
            $token = $this->tokenParser->parse($cookies[$cookieName]);
        } catch (InvalidArgumentException $invalidToken) {
            return null;
        }

        $constraints = [
            new ValidAt($this->clock),
            new SignedWith($this->signer, $this->verificationKey),
        ];

        if (! (new Validator())->validate($token, ...$constraints)) {
            return null;
        }

        return $token;
    }

    /**
     * @throws OutOfBoundsException
     */
    private function extractSessionContainer(?Token $token): SessionInterface
    {
        if (! $token) {
            return DefaultSessionData::newEmptySession();
        }

        try {
            return DefaultSessionData::fromDecodedTokenData(
                (object) $token->claims()->get(self::SESSION_CLAIM, new stdClass())
            );
        } catch (BadMethodCallException $invalidToken) {
            return DefaultSessionData::newEmptySession();
        }
    }

    /**
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    private function appendToken(SessionInterface $sessionContainer, Response $response, ?Token $token): Response
    {
        $sessionContainerChanged = $sessionContainer->hasChanged();

        if ($sessionContainerChanged && $sessionContainer->isEmpty()) {
            return FigResponseCookies::set($response, $this->getExpirationCookie());
        }

        if ($sessionContainerChanged || ($this->shouldTokenBeRefreshed($token) && ! $sessionContainer->isEmpty())) {
            return FigResponseCookies::set($response, $this->getTokenCookie($sessionContainer));
        }

        return $response;
    }

    private function shouldTokenBeRefreshed(?Token $token): bool
    {
        if (! ($token && $token->claims()->has(self::ISSUED_AT_CLAIM))) {
            return false;
        }

        $issuedAt = $token->claims()->get(self::ISSUED_AT_CLAIM);

        assert($issuedAt instanceof DateTimeImmutable);

        return $this->clock->now() >= $issuedAt->add(new DateInterval(sprintf('PT%sS', $this->refreshTime)));
    }

    /**
     * @throws BadMethodCallException
     */
    private function getTokenCookie(SessionInterface $sessionContainer): SetCookie
    {
        $now       = $this->clock->now();
        $expiresAt = $now->add(new DateInterval(sprintf('PT%sS', $this->expirationTime)));

        return $this
            ->defaultCookie
            ->withValue(
                (new Builder())
                    ->setIssuedAt($now)
                    ->setExpiration($expiresAt)
                    ->set(self::SESSION_CLAIM, $sessionContainer)
                    ->getToken($this->signer, $this->signatureKey)
                    ->toString()
            )
            ->withExpires($expiresAt);
    }

    private function getExpirationCookie(): SetCookie
    {
        $expirationDate = $this->clock->now()->modify('-30 days');

        return $this
            ->defaultCookie
            ->withValue(null)
            ->withExpires($expirationDate->getTimestamp());
    }
}
