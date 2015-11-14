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

namespace StoragelessSessionTest\Session;

use PHPUnit_Framework_TestCase;
use StoragelessSession\Session\DefaultSessionData;
use StoragelessSession\Session\LazySession;
use StoragelessSession\Session\SessionInterface;

/**
 * @covers \StoragelessSession\Session\LazySession
 */
final class LazySessionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var SessionInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $wrappedSession;

    /**
     * @var callable|\PHPUnit_Framework_MockObject_MockObject
     */
    private $sessionLoader;

    /**
     * @var LazySession
     */
    private $lazySession;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $this->wrappedSession = $this->getMock(SessionInterface::class);
        $this->sessionLoader  = $this->getMock(\stdClass::class, ['__invoke']);
        $this->lazySession    = LazySession::fromContainerBuildingCallback($this->sessionLoader);
    }

    public function testIsALazySession()
    {
        self::assertInstanceOf(LazySession::class, $this->lazySession);
    }

    public function testLazyNonInitializedSessionIsAlwaysNotChanged()
    {
        $this->sessionLoader->expects($this->never())->method('__invoke');

        self::assertFalse($this->lazySession->hasChanged());
    }
}
