<?php

namespace PSR7SessionTest\Session;

use PHPUnit_Framework_TestCase;
use PSR7Session\Id\SessionId;
use PSR7Session\Session\DefaultSessionData;
use PSR7Session\Session\SessionInterface;
use PSR7Session\Session\StorableSession;
use PSR7Session\Session\StorableSessionInterface;
use PSR7Session\Storage\MemoryStorage;
use PSR7Session\Storage\StorageInterface;

class StorableSessionTest extends PHPUnit_Framework_TestCase
{
    /** @var SessionInterface */
    private $wrappedSession;
    /** @var StorageInterface */
    private $storage;
    /** @var StorableSession */
    private $session;

    public function setUp()
    {
        $this->wrappedSession = DefaultSessionData::newEmptySession();
        $this->storage = new MemoryStorage;
        $this->session = new StorableSession($this->wrappedSession, $this->storage);
    }

    public function testGet()
    {
        $key = 'test';
        $val = 'foo';
        $this->wrappedSession->set($key, $val);

        $this->assertSame($val, $this->session->get($key));
    }

    public function testRemove()
    {
        $key = 'test';
        $this->session->set($key, 'bar');

        $this->session->remove($key);

        $this->assertFalse($this->session->has($key));
    }

    public function testClear()
    {
        $this->session->set('foo', 'bar');

        $this->session->clear();

        $this->assertFalse($this->session->has('foo'));
    }

    public function testHasChanged()
    {
        $this->assertFalse($this->session->hasChanged());

        $this->session->set('foo', 'bar');

        $this->assertTrue($this->session->hasChanged());
    }

    public function testIsEmpty()
    {
        $this->assertTrue($this->session->isEmpty());

        $this->session->set('foo', 'bar');

        $this->assertFalse($this->session->isEmpty());
    }

    public function testFromStorage()
    {
        $id = new SessionId('test');
        $wrappedSession = DefaultSessionData::newEmptySession();
        $wrappedSession->set('foo', 'bar');

        $session = StorableSession::fromId($wrappedSession, $this->storage, $id);

        $this->assertSame($id, $session->getId());
        $this->assertSame('bar', $session->get('foo'));
    }

    public function testSaveOnSet()
    {
        $this->session->set('foo', 'bar');

        $loaded = $this->reload($this->session);
        $this->assertSame('bar', $loaded->get('foo'));
    }

    public function testSaveOnRemove()
    {
        $this->session->set('foo', 'bar');

        $this->session->remove('foo');

        $loaded = $this->reload($this->session);
        $this->assertFalse($loaded->has('foo'));
    }

    public function testSaveOnClear()
    {
        $this->session->set('foo', 'bar');

        $this->session->clear();

        $loaded = $this->reload($this->session);
        $this->assertFalse($loaded->has('foo'));
    }

    private function reload(StorableSessionInterface $session):StorableSessionInterface
    {
        return $this->storage->load($session->getId());
    }
}
