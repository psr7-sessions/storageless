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

use DateTimeImmutable;
use Lcobucci\Clock\FrozenClock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PSR7Sessions\Storageless\Session\SessionInterface;
use PSR7Sessions\Storageless\Session\Zend\SessionAdapter;
use stdClass;

/**
 * @covers \PSR7Sessions\Storageless\Session\Zend\SessionAdapter
 */
final class SessionAdapterTest extends TestCase
{
    public function testToArray() : void
    {
        $object      = new stdClass();
        $object->key = 'value';

        /** @var SessionInterface|MockObject $session */
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $session->expects(self::once())->method('jsonSerialize')->with()->willReturn($object);

        $sessionAdapter = new SessionAdapter($session);

        self::assertSame(['key' => 'value'], $sessionAdapter->toArray());
    }

    public function testGet() : void
    {
        /** @var SessionInterface|MockObject $session */
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $session->expects(self::once())->method('get')->with('key', null)->willReturn('value');

        $sessionAdapter = new SessionAdapter($session);

        self::assertSame('value', $sessionAdapter->get('key'));
    }

    public function testHas() : void
    {
        /** @var SessionInterface|MockObject $session */
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $session->expects(self::once())->method('has')->with('key')->willReturn(true);

        $sessionAdapter = new SessionAdapter($session);

        self::assertTrue($sessionAdapter->has('key'));
    }

    public function testSet() : void
    {
        /** @var SessionInterface|MockObject $session */
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $session->expects(self::once())->method('set')->with('key', 'value');

        $sessionAdapter = new SessionAdapter($session);
        $sessionAdapter->set('key', 'value');
    }

    public function testUnset() : void
    {
        /** @var SessionInterface|MockObject $session */
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $session->expects(self::once())->method('remove')->with('key');

        $sessionAdapter = new SessionAdapter($session);
        $sessionAdapter->unset('key');
    }

    public function testClear() : void
    {
        /** @var SessionInterface|MockObject $session */
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $session->expects(self::once())->method('clear')->with();

        $sessionAdapter = new SessionAdapter($session);
        $sessionAdapter->clear();
    }

    public function testHasChanged() : void
    {
        /** @var SessionInterface|MockObject $session */
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $session->expects(self::once())->method('hasChanged')->with()->willReturn(true);

        $sessionAdapter = new SessionAdapter($session);

        self::assertTrue($sessionAdapter->hasChanged());
    }

    public function testRegenerate() : void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2019-01-01T00:00:00+00:00'));

        /** @var SessionInterface|MockObject $session */
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $session->expects(self::once())->method('set')->with('_regenerated', $clock->now()->getTimestamp());

        $sessionAdapter = new SessionAdapter($session, $clock);
        $sessionAdapter->regenerate();
    }

    public function testIsRegenerated() : void
    {
        /** @var SessionInterface|MockObject $session */
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $session->expects(self::once())->method('has')->with('_regenerated')->willReturn(true);

        $sessionAdapter = new SessionAdapter($session);

        self::assertTrue($sessionAdapter->isRegenerated());
    }
}
