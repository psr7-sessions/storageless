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
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSR7Sessions\Storageless\Service\StoragelessSession;
use PSR7Sessions\Storageless\Session\LazySession;

/** @immutable */
final class SessionMiddleware implements MiddlewareInterface
{
    public const SESSION_CLAIM     = 'session-data';
    public const SESSION_ATTRIBUTE = 'session';

    private readonly StoragelessSession $service;

    public function __construct(
        private readonly Configuration $config,
    ) {
        $this->service = new StoragelessSession($config);
    }

    /**
     * {@inheritDoc}
     *
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        return $this->service->appendSession(
            LazySession::fromToken($this->service->requestToToken($request)),
            $request,
            null,
            $handler,
        );
    }
}
