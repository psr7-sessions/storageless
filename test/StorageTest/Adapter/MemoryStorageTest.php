<?php

namespace PSR7SessionsTest\Storage\Adapter;

use PHPUnit_Framework_TestCase;
use PSR7Sessions\Storage\Adapter\MemoryStorage;
use PSR7Sessions\Storage\Id\UuidSessionId;
use PSR7Sessions\Storage\Session\StorableSession;
use PSR7Sessions\Storage\Session\StorableSessionInterface;

class MemoryStorageTest extends PHPUnit_Framework_TestCase
{
    /** @var MemoryStorage */
    private $storage;

    protected function setUp()
    {
        parent::setUp();

        $this->storage = new MemoryStorage;
    }

    public function testSaveAndLoad()
    {
        $session = $this->createSession();
        $session->set('foo', 'bar');

        $this->storage->save($session);
        $loaded = $this->reload($session);

        $this->assertSame('bar', $loaded->get('foo'));
    }

    public function testLoadUnknownId()
    {
        $id = new UuidSessionId;
        $session = $this->storage->load($id);

        $this->assertSame($id, $session->getId());
        $this->assertTrue($session->isEmpty());
    }

    public function testDestroy()
    {
        $session = $this->createSession();
        $session->set('foo', 'bar');
        $this->storage->save($session);

        $this->storage->destroy($session->getId());

        $loaded = $this->reload($session);
        $this->assertTrue($loaded->isEmpty());
    }

    public function testDestroyUnknownSession()
    {
        $id = new UuidSessionId;
        $this->storage->destroy($id);

        $loaded = $this->storage->load($id);
        $this->assertTrue($loaded->isEmpty());
    }

    private function createSession():StorableSession
    {
        return StorableSession::create($this->storage);
    }

    private function reload(StorableSessionInterface $session):StorableSessionInterface
    {
        return $this->storage->load($session->getId());
    }
}
