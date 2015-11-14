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

    public function testHasChanged()
    {
        $this->wrappedSessionWillBeLoaded();

        $this->wrappedSession->expects($this->at(1))->method('hasChanged')->willReturn(true);
        $this->wrappedSession->expects($this->at(2))->method('hasChanged')->willReturn(false);

        $this->forceWrappedSessionInitialization();

        self::assertTrue($this->lazySession->hasChanged());
        self::assertFalse($this->lazySession->hasChanged());
    }

    public function testHas()
    {
        $this->wrappedSessionWillBeLoaded();

        $this->wrappedSession->expects($this->exactly(2))->method('has')->willReturnMap([
            ['foo', false],
            ['bar', true],
        ]);

        self::assertFalse($this->lazySession->has('foo'));
        self::assertTrue($this->lazySession->has('bar'));
    }

    public function testGet()
    {
        $this->wrappedSessionWillBeLoaded();

        $this->wrappedSession->expects($this->exactly(3))->method('get')->willReturnMap([
            ['foo', null, 'bar'],
            ['baz', null, 'tab'],
            ['baz', 'default', 'taz'],
        ]);

        self::assertSame('bar', $this->lazySession->get('foo'));
        self::assertSame('tab', $this->lazySession->get('baz'));
        self::assertSame('taz', $this->lazySession->get('baz', 'default'));
    }

    public function testRemove()
    {
        $this->wrappedSessionWillBeLoaded();

        $this->wrappedSession->expects($this->exactly(2))->method('remove')->with(self::logicalOr('foo', 'bar'));

        $this->lazySession->remove('foo');
        $this->lazySession->remove('bar');
    }

    public function testClear()
    {
        $this->wrappedSessionWillBeLoaded();

        $this->wrappedSession->expects($this->exactly(2))->method('clear');

        $this->lazySession->clear();
        $this->lazySession->clear();
    }

    public function testSet()
    {
        $this->wrappedSessionWillBeLoaded();

        $this
            ->wrappedSession
            ->expects($this->exactly(2))
            ->method('set')
            ->with(self::logicalOr('foo', 'baz'), self::logicalOr('bar', 'tab'));

        $this->lazySession->set('foo', 'bar');
        $this->lazySession->set('baz', 'tab');
    }

    public function testIsEmpty()
    {
        $this->wrappedSessionWillBeLoaded();

        $this->wrappedSession->expects($this->at(0))->method('isEmpty')->willReturn(true);
        $this->wrappedSession->expects($this->at(1))->method('isEmpty')->willReturn(false);

        self::assertTrue($this->lazySession->isEmpty());
        self::assertFalse($this->lazySession->isEmpty());
    }

    public function testJsonSerialize()
    {
        $this->wrappedSessionWillBeLoaded();

        $this->wrappedSession->expects($this->at(0))->method('jsonSerialize')->willReturn('foo');
        $this->wrappedSession->expects($this->at(1))->method('jsonSerialize')->willReturn('bar');

        self::assertSame('foo', $this->lazySession->jsonSerialize());
        self::assertSame('bar', $this->lazySession->jsonSerialize());
    }

    private function wrappedSessionWillBeLoaded()
    {
        $this->sessionLoader->expects($this->once())->method('__invoke')->willReturn($this->wrappedSession);
    }

    private function forceWrappedSessionInitialization()
    {
        // no-op operation that is known to trigger session lazy-loading
        $this->lazySession->remove(uniqid('nonExisting', true));
    }
}
