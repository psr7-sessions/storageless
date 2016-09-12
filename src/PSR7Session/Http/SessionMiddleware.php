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

namespace PSR7Session\Http;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Zend\Stratigility\MiddlewareInterface;

final class SessionMiddleware implements MiddlewareInterface
{
    use SessionMiddlewareTrait;

    const ISSUED_AT_CLAIM      = 'iat';
    const SESSION_CLAIM        = 'session-data';
    const SESSION_ATTRIBUTE    = 'session';
    const DEFAULT_COOKIE       = 'slsession';
    const DEFAULT_REFRESH_TIME = 60;

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     * @throws \OutOfBoundsException
     */
    public function __invoke(Request $request, Response $response, callable $out = null) : Response
    {
        $token            = $this->parseToken($request);
        $sessionContainer = $this->createLazySession($token);

        if (null !== $out) {
            $response = $out($request->withAttribute(self::SESSION_ATTRIBUTE, $sessionContainer), $response);
        }

        return $this->appendToken($sessionContainer, $response, $token);
    }
}
