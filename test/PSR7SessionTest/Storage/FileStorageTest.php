<?php

declare(strict_types = 1);

namespace PSR7SessionTest\Storage;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use PSR7Session\Id\Factory\SessionIdFactoryInterface;
use PSR7Session\Id\SessionId;
use PSR7Session\Id\SessionIdInterface;
use PSR7Session\Session\DefaultSessionData;
use PSR7Session\Session\StorableSession;
use PSR7Session\Session\StorableSessionInterface;
use PSR7Session\Storage\FileStorage;

class FileStorageTest extends PHPUnit_Framework_TestCase
{
    /** @var vfsStreamDirectory */
    private $fileSystem;
    /** @var FileStorage */
    private $storage;
    /** @var SessionIdFactoryInterface|PHPUnit_Framework_MockObject_MockObject */
    private $idFactory;

    public function setUp()
    {
        $this->fileSystem = vfsStream::setup();
        $this->idFactory = $this->getMock(SessionIdFactoryInterface::class);
        $this->storage = new FileStorage($this->fileSystem->url(), $this->idFactory);
    }

    public function testSaveNewSession()
    {
        $session = $this->createSession();
        $session->set('test', 'foo');
        $id = $this->createId('my-id');
        $this->idFactory
            ->method('create')
            ->willReturn($id);

        $this->storage->save($session);

        $loadedSession = $this->storage->load($session->getId());
        $this->assertEquals($session->get('test'), $loadedSession->get('test'));
    }

    public function testDestroy()
    {
        $session = $this->createSession();
        $id = $this->createId('my-id');
        $this->idFactory
            ->method('create')
            ->willReturn($id);
        $this->storage->save($session);

        $this->storage->destroy($session->getId());

        $this->assertNull($this->storage->load($session->getId()));
    }

    /**
     * @return StorableSessionInterface
     */
    private function createSession()
    {
        $innerSession = DefaultSessionData::newEmptySession();
        return StorableSession::create($innerSession, $this->storage);
    }

    /**
     * @return SessionIdInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private function createId(string $id)
    {
        return new SessionId($id);
    }
}
