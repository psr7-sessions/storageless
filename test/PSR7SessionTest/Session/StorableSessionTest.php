<?php

namespace PSR7SessionTest\Session;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use PSR7Session\Id\SessionId;
use PSR7Session\Session\DefaultSessionData;
use PSR7Session\Session\SessionInterface;
use PSR7Session\Session\StorableSession;
use PSR7Session\Storage\StorageInterface;

class StorableSessionTest extends PHPUnit_Framework_TestCase
{
    /** @var SessionInterface|PHPUnit_Framework_MockObject_MockObject */
    private $wrappedSession;
    /** @var StorageInterface|PHPUnit_Framework_MockObject_MockObject */
    private $storage;
    /** @var StorableSession */
    private $session;

    public function setUp()
    {
        $this->wrappedSession = $this->getMock(SessionInterface::class);
        $this->storage = $this->getMock(StorageInterface::class);
        $this->session = StorableSession::create($this->wrappedSession, $this->storage);
    }

    public function testGet()
    {
        $key = 'test';
        $val = 'foo';
        $this->wrappedSession
            ->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn($val);

        $this->assertSame($val, $this->session->get($key));
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

    public function testFromStorage()
    {
        $id = new SessionId('test');
        $wrappedSession = DefaultSessionData::newEmptySession();
        $wrappedSession->set('foo', 'bar');
        $this->storage
            ->method('load')
            ->with($id)
            ->willReturn($wrappedSession);

        $session = StorableSession::fromStorage($this->storage, $id);

        $this->assertSame($id, $session->getId());
        $this->assertSame('bar', $session->get('foo'));
    }

    public function testSaveOnSet()
    {
        $this->storage
            ->expects($this->once())
            ->method('save')
            ->with($this->session);

        $this->session->set('foo', 'bar');
    }

    public function testSaveOnRemove()
    {
        $this->session->set('foo', 'bar');
        $this->storage
            ->expects($this->once())
            ->method('save')
            ->with($this->session);

        $this->session->remove('foo');
    }

    public function testSaveOnClear()
    {
        $this->session->set('foo', 'bar');
        $this->storage
            ->expects($this->once())
            ->method('save')
            ->with($this->session);

        $this->session->clear();
    }
}
