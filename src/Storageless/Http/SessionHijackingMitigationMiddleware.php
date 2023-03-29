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

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSR7Sessions\Storageless\Session\SessionInterface;
use RuntimeException;

use function array_key_exists;
use function assert;
use function base64_encode;
use function is_string;
use function sodium_crypto_generichash;
use function sprintf;

final class SessionHijackingMitigationMiddleware implements MiddlewareInterface
{
    public const SERVER_PARAM_REMOTE_ADDR = 'REMOTE_ADDR';
    public const SERVER_PARAM_USER_AGENT  = 'HTTP_USER_AGENT';

    public const SESSION_KEY = 'fp';

    /** @param literal-string $sessionAttribute */
    public function __construct(
        private readonly string $sessionAttribute = SessionMiddleware::SESSION_ATTRIBUTE,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $session = $request->getAttribute($this->sessionAttribute);
        assert($session instanceof SessionInterface);
        $fingerprint = $this->getFingerprint($request);

        if (! $session->isEmpty()) {
            if ($fingerprint !== $session->get(self::SESSION_KEY)) {
                $session->clear();
            }
        }

        $response = $handler->handle($request);

        if (! $session->isEmpty()) {
            $session->set(self::SESSION_KEY, $fingerprint);
        }

        return $response;
    }

    /** @return non-empty-string */
    private function getFingerprint(Request $request): string
    {
        $serverParams = $request->getServerParams();
        if (
            ! array_key_exists(self::SERVER_PARAM_REMOTE_ADDR, $serverParams)
            || ! is_string($serverParams[self::SERVER_PARAM_REMOTE_ADDR])
            || $serverParams[self::SERVER_PARAM_REMOTE_ADDR] === ''
        ) {
            throw new RuntimeException(sprintf(
                'The request lacks a valid %s parameter',
                self::SERVER_PARAM_REMOTE_ADDR,
            ));
        }

        if (
            ! array_key_exists(self::SERVER_PARAM_USER_AGENT, $serverParams)
            || ! is_string($serverParams[self::SERVER_PARAM_USER_AGENT])
            || $serverParams[self::SERVER_PARAM_USER_AGENT] === ''
        ) {
            throw new RuntimeException(sprintf(
                'The request lacks a valid %s parameter',
                self::SERVER_PARAM_USER_AGENT,
            ));
        }

        $fingerprint = base64_encode(sodium_crypto_generichash(sprintf(
            '%s#%s',
            $serverParams[self::SERVER_PARAM_REMOTE_ADDR],
            $serverParams[self::SERVER_PARAM_USER_AGENT],
        )));
        assert($fingerprint !== '');

        return $fingerprint;
    }
}
