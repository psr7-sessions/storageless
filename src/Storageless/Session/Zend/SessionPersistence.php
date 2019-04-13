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

namespace PSR7Sessions\Storageless\Session\Zend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Session\SessionInterface;
use UnexpectedValueException;
use Zend\Expressive\Session\SessionInterface as ZendSessionInterface;
use Zend\Expressive\Session\SessionPersistenceInterface as ZendSessionPersistenceInterface;
use function sprintf;

final class SessionPersistence implements ZendSessionPersistenceInterface
{
    public function initializeSessionFromRequest(ServerRequestInterface $request) : ZendSessionInterface
    {
        /** @var SessionInterface|null $storagelessSession */
        $storagelessSession = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        if (! $storagelessSession instanceof SessionInterface) {
            throw new UnexpectedValueException(
                sprintf(
                    'Please add this following middleware "%s" before execute this method "%s"',
                    SessionMiddleware::class,
                    __METHOD__
                )
            );
        }

        return new SessionAdapter($storagelessSession);
    }

    public function persistSession(ZendSessionInterface $session, ResponseInterface $response) : ResponseInterface
    {
        return $response;
    }
}
