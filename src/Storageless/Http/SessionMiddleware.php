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

use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use Lcobucci\Clock\Clock;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\ValidationData;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSR7Sessions\Storageless\Session\DefaultSessionData;
use PSR7Sessions\Storageless\Session\LazySession;
use PSR7Sessions\Storageless\Session\SessionInterface;

final class SessionMiddleware implements MiddlewareInterface
{
    public const ISSUED_AT_CLAIM      = 'iat';
    public const SESSION_CLAIM        = 'session-data';
    public const SESSION_ATTRIBUTE    = 'session';
    public const DEFAULT_COOKIE       = 'slsession';
    public const DEFAULT_REFRESH_TIME = 60;

    /**
     * @var Signer
     */
    private $signer;

    /**
     * @var string
     */
    private $signatureKey;

    /**
     * @var string
     */
    private $verificationKey;

    /**
     * @var int
     */
    private $expirationTime;

    /**
     * @var int
     */
    private $refreshTime;

    /**
     * @var Parser
     */
    private $tokenParser;

    /**
     * @var SetCookie
     */
    private $defaultCookie;

    /**
     * @var Clock
     */
    private $clock;

    /**
     * @param Signer    $signer
     * @param string    $signatureKey
     * @param string    $verificationKey
     * @param SetCookie $defaultCookie
     * @param Parser    $tokenParser
     * @param int       $expirationTime
     * @param Clock     $clock
     * @param int       $refreshTime
     */
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
        $this->signatureKey    = $signatureKey;
        $this->verificationKey = $verificationKey;
        $this->tokenParser     = $tokenParser;
        $this->defaultCookie   = clone $defaultCookie;
        $this->expirationTime  = $expirationTime;
        $this->clock           = $clock;
        $this->refreshTime     = $refreshTime;
    }

    /**
     * This constructor simplifies instantiation when using HTTPS (REQUIRED!) and symmetric key encryption
     */
    public static function fromSymmetricKeyDefaults(string $symmetricKey, int $expirationTime) : self
    {
        return new self(
            new Signer\Hmac\Sha256(),
            $symmetricKey,
            $symmetricKey,
            SetCookie::create(self::DEFAULT_COOKIE)
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withPath('/'),
            new Parser(),
            $expirationTime,
            new SystemClock()
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
    ) : self {
        return new self(
            new Signer\Rsa\Sha256(),
            $privateRsaKey,
            $publicRsaKey,
            SetCookie::create(self::DEFAULT_COOKIE)
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withPath('/'),
            new Parser(),
            $expirationTime,
            new SystemClock()
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function process(Request $request, RequestHandlerInterface $delegate) : Response
    {
        $token            = $this->parseToken($request);
        $sessionContainer = LazySession::fromContainerBuildingCallback(function () use ($token) : SessionInterface {
            return $this->extractSessionContainer($token);
        });

        return $this->appendToken(
            $sessionContainer,
            $delegate->handle($request->withAttribute(self::SESSION_ATTRIBUTE, $sessionContainer)),
            $token
        );
    }

    /**
     * Extract the token from the given request object
     */
    private function parseToken(Request $request) : ?Token
    {
        $cookies    = $request->getCookieParams();
        $cookieName = $this->defaultCookie->getName();

        if (! isset($cookies[$cookieName])) {
            return null;
        }

        try {
            $token = $this->tokenParser->parse($cookies[$cookieName]);
        } catch (\InvalidArgumentException $invalidToken) {
            return null;
        }

        if (! $token->validate(new ValidationData())) {
            return null;
        }

        return $token;
    }

    /**
     * @throws \OutOfBoundsException
     */
    private function extractSessionContainer(?Token $token) : SessionInterface
    {
        try {
            if (null === $token || ! $token->verify($this->signer, $this->verificationKey)) {
                return DefaultSessionData::newEmptySession();
            }

            return DefaultSessionData::fromDecodedTokenData(
                (object) $token->getClaim(self::SESSION_CLAIM, new \stdClass())
            );
        } catch (\BadMethodCallException $invalidToken) {
            return DefaultSessionData::newEmptySession();
        }
    }

    /**
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    private function appendToken(SessionInterface $sessionContainer, Response $response, ?Token $token) : Response
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

    private function shouldTokenBeRefreshed(?Token $token) : bool
    {
        if (! $token || ! $token->hasClaim(self::ISSUED_AT_CLAIM)) {
            return false;
        }

        return $this->timestamp() >= ($token->getClaim(self::ISSUED_AT_CLAIM) + $this->refreshTime);
    }

    /**
     * @throws \BadMethodCallException
     */
    private function getTokenCookie(SessionInterface $sessionContainer) : SetCookie
    {
        $timestamp = $this->timestamp();

        return $this
            ->defaultCookie
            ->withValue(
                (new Builder())
                    ->setIssuedAt($timestamp)
                    ->setExpiration($timestamp + $this->expirationTime)
                    ->set(self::SESSION_CLAIM, $sessionContainer)
                    ->sign($this->signer, $this->signatureKey)
                    ->getToken()
            )
            ->withExpires($timestamp + $this->expirationTime);
    }

    private function getExpirationCookie() : SetCookie
    {
        $expirationDate = $this->clock->now()->modify('-30 days');

        return $this
            ->defaultCookie
            ->withValue(null)
            ->withExpires($expirationDate->getTimestamp());
    }

    private function timestamp() : int
    {
        return $this->clock->now()->getTimestamp();
    }
}
