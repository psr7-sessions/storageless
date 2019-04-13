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

namespace PSR7SessionsTest\Storageless\Session\Zend;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Session\SessionInterface;
use PSR7Sessions\Storageless\Session\Zend\SessionPersistence;
use UnexpectedValueException;
use Zend\Expressive\Session\SessionInterface as ZendSessionInterface;
use function sprintf;

/**
 * @covers \PSR7Sessions\Storageless\Session\Zend\SessionPersistence
 */
final class SessionPersistenceTest extends TestCase
{
    public function testInitializeSessionFromRequestWithMissingPsr7SessionAttribute() : void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(sprintf(
            'Please add this following middleware "%s" before execute this method "%s::initializeSessionFromRequest"',
            SessionMiddleware::class,
            SessionPersistence::class
        ));

        /** @var ServerRequestInterface|MockObject $request */
        $request = $this->getMockBuilder(ServerRequestInterface::class)->getMock();
        $request
            ->expects(self::once())
            ->method('getAttribute')
            ->with(SessionMiddleware::SESSION_ATTRIBUTE, null)
            ->willReturn(null);

        $persistence = new SessionPersistence();
        $persistence->initializeSessionFromRequest($request);
    }

    public function testInitializeSessionFromRequestWithPsr7SessionAttribute() : void
    {
        /** @var SessionInterface|MockObject $session */
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $session->expects(self::never())->method(self::anything());

        /** @var ServerRequestInterface|MockObject $request */
        $request = $this->getMockBuilder(ServerRequestInterface::class)->getMock();
        $request
            ->expects(self::once())
            ->method('getAttribute')
            ->with(SessionMiddleware::SESSION_ATTRIBUTE, null)
            ->willReturn($session);

        $persistence = new SessionPersistence();
        $persistence->initializeSessionFromRequest($request);
    }

    public function testPersistSession() : void
    {
        /** @var ZendSessionInterface|MockObject $zendSession */
        $zendSession = $this->getMockBuilder(ZendSessionInterface::class)->getMock();
        $zendSession->expects(self::never())->method(self::anything());

        /** @var ResponseInterface|MockObject $response */
        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $response->expects(self::never())->method(self::anything());

        $persistence = new SessionPersistence();

        self::assertSame($response, $persistence->persistSession($zendSession, $response));
    }
}
