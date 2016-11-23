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

declare(strict_types = 1);

namespace PSR7SessionsTest\Storageless\Session;

use PHPUnit_Framework_TestCase;
use PSR7Sessions\Storageless\Session\LazySession;
use PSR7Sessions\Storageless\Session\ArrayAccessor;
use PSR7Sessions\Storageless\Session\SessionInterface;

/**
 * @covers \PSR7Sessions\Storageless\Session\ArrayAccessor
 */
final class SessionArrayAccessorTest extends PHPUnit_Framework_TestCase
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
     * @var ArrayAccessor
     */
    private $arrayAccess;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $this->wrappedSession = $this->createMock(SessionInterface::class);
        $this->sessionLoader = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $session = LazySession::fromContainerBuildingCallback($this->sessionLoader);

        $this->arrayAccess = new ArrayAccessor($session);
    }

    public function testIsAnSessionArrayAccessor()
    {
        self::assertInstanceOf(ArrayAccessor::class, $this->arrayAccess);
    }

    public function testOffsetExists()
    {
        $this->wrappedSessionWillBeLoaded();
        $this->wrappedSession->expects(self::exactly(2))->method('has')->willReturnMap(
            [
                ['foo', false],
                ['bar', true],
            ]
        );

        self::assertFalse($this->arrayAccess->offsetExists('foo'));
        self::assertTrue($this->arrayAccess->offsetExists('bar'));
    }

    /**
     *
     */
    public function testOffsetUnset()
    {
        $this->wrappedSessionWillBeLoaded();
        $this->wrappedSession->expects(self::exactly(2))->method('remove')->with(self::logicalOr('foo', 'bar'));

        $this->arrayAccess->offsetUnset('foo');
        $this->arrayAccess->offsetUnset('bar');
    }

    public function testOffsetGet()
    {
        $this->wrappedSessionWillBeLoaded();
        $this->wrappedSession->expects(self::exactly(3))->method('get')->willReturnMap(
            [
                ['foo', null, 'bar'],
                ['baz', null, 'tab'],
                ['baz', 'default', 'taz'],
            ]
        );

        self::assertSame('bar', $this->arrayAccess->offsetGet('foo'));
        self::assertSame('tab', $this->arrayAccess->offsetGet('baz'));
        self::assertSame('taz', $this->arrayAccess->offsetGet('baz', 'default'));
    }

    public function testOffsetSet()
    {
        $this->wrappedSessionWillBeLoaded();
        $this->wrappedSession->expects(self::exactly(2))->method('set')->with(
            self::logicalOr('foo', 'baz'),
            self::logicalOr('bar', 'tab')
        );

        $this->arrayAccess->offsetSet('foo', 'bar');
        $this->arrayAccess->offsetSet('baz', 'tab');
    }

    public function testIsEmpty()
    {
        $this->wrappedSessionWillBeLoaded();

        $this->wrappedSession->expects(self::at(0))->method('isEmpty')->willReturn(true);
        $this->wrappedSession->expects(self::at(1))->method('isEmpty')->willReturn(false);

        self::assertTrue($this->arrayAccess->isEmpty());
        self::assertFalse($this->arrayAccess->isEmpty());
    }

    private function wrappedSessionWillBeLoaded()
    {
        $this->sessionLoader->expects(self::once())->method('__invoke')->willReturn($this->wrappedSession);
    }
}
