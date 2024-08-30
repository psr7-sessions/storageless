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

use BadMethodCallException;
use DateInterval;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use InvalidArgumentException;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSR7Sessions\Storageless\Http\ClientFingerprint\SameOriginRequest;
use PSR7Sessions\Storageless\Http\Configuration;
use PSR7Sessions\Storageless\Session\LazySession;
use PSR7Sessions\Storageless\Session\SessionInterface;

use function sprintf;

final class StoragelessSession implements SessionStorage
{
    public const SESSION_CLAIM = 'session-data';

    public function __construct(
        private readonly Configuration $config,
    ) {
    }

    public function appendSession(SessionInterface $session, ServerRequestInterface $request, Response|null $response = null, RequestHandlerInterface|null $handler = null): Response
    {
        $sameOriginRequest = $this->getSameOriginRequest($request);

        $handler ??= fn (ResponseInterface $response): RequestHandlerInterface => new class ($response) implements RequestHandlerInterface {
            public function __construct(
                private readonly ResponseInterface $response,
            ) {
            }

            public function handle(Request $request): ResponseInterface
            {
              return $this->response;
            }
        };

        $response ??= $handler->handle($request->withAttribute($this->config->getSessionAttribute(), $session));

        return $this->appendToken(
            $session,
            $response,
            $this->requestToToken($request, $sameOriginRequest),
            $sameOriginRequest,
        );
    }

    public function getSession(Request $request): SessionInterface
    {
        return LazySession::fromToken($this->requestToToken($request));
    }

    public function requestToToken(Request $request, SameOriginRequest|null $sameOriginRequest = null): UnencryptedToken|null
    {
        /** @var array<string, string> $cookies */
        $cookies    = $request->getCookieParams();
        $cookieName = $this->config->getCookie()->getName();

        $cookie = $cookies[$cookieName] ?? '';

        if ($cookie === '') {
            return null;
        }

        $jwtConfiguration = $this->config->getJwtConfiguration();
        try {
            $token = $jwtConfiguration->parser()->parse($cookie);
        } catch (InvalidArgumentException) {
            return null;
        }

        if (! $token instanceof UnencryptedToken) {
            return null;
        }

        $constraints = [
            new StrictValidAt($this->config->getClock()),
            new SignedWith($jwtConfiguration->signer(), $jwtConfiguration->verificationKey()),
            $sameOriginRequest ?? $this->getSameOriginRequest($request),
        ];

        if (! $jwtConfiguration->validator()->validate($token, ...$constraints)) {
            return null;
        }

        return $token;
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
            return FigResponseCookies::set(
                $response,
                $this
                    ->config
                    ->getCookie()
                    ->withValue(null)
                    ->withExpires(
                        $this->config->getClock()
                            ->now()
                            ->modify('-30 days'),
                    ),
            );
        }

        if ($sessionContainerChanged || $this->shouldTokenBeRefreshed($token)) {
            return FigResponseCookies::set($response, $this->getTokenCookie($sessionContainer, $sameOriginRequest));
        }

        return $response;
    }

    private function getSameOriginRequest(Request $request): SameOriginRequest
    {
        return new SameOriginRequest($this->config->getClientFingerprintConfiguration(), $request);
    }

    /** @throws BadMethodCallException */
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
}
