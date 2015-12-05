<?php

namespace PSR7SessionTest\Session;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use PSR7Session\Session\SessionInterface;
use PSR7Session\Session\StorableSession;

class StorableSessionTest extends PHPUnit_Framework_TestCase
{
    /** @var SessionInterface|PHPUnit_Framework_MockObject_MockObject */
    private $wrappedSession;
    /** @var StorableSession */
    private $session;

    public function setUp()
    {
        $this->wrappedSession = $this->getMock(SessionInterface::class);
        $this->session = new StorableSession($this->wrappedSession);
    }

    public function testRemoveIsDelegated()
    {
        $key = 'test';
        $this->wrappedSession
            ->expects($this->once())
            ->method('remove')
            ->with($key);

        $this->session->remove($key);
    }

    public function testClearIsDelegated()
    {
        $this->wrappedSession
            ->expects($this->once())
            ->method('clear');

        $this->session->clear();
    }

    public function testHasIsDelegated()
    {
        $key = 'test';
        $this->wrappedSession
            ->expects($this->once())
            ->method('has')
            ->with($key)
            ->willReturn(true);

        $has = $this->session->has($key);

        $this->assertTrue($has);
    }

    public function testHasChangedIsDelegated()
    {
        $this->wrappedSession
            ->expects($this->once())
            ->method('hasChanged')
            ->willReturn(true);

        $hasChanged = $this->session->hasChanged();

        $this->assertTrue($hasChanged);
    }

    public function testIsEmptyIsDelegated()
    {
        $this->wrappedSession
            ->expects($this->once())
            ->method('isEmpty')
            ->willReturn(true);

        $isEmpty = $this->session->isEmpty();

        $this->assertTrue($isEmpty);
    }
}
