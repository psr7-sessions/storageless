<?php

namespace PSR7Sessions\Storage\Session;

use PSR7Sessions\Storage\Adapter\StorageInterface;
use PSR7Sessions\Storage\Id\SessionIdInterface;
use PSR7Sessions\Storage\Id\UuidSessionId;
use PSR7Sessions\Storageless\Session\DefaultSessionData;
use PSR7Sessions\Storageless\Session\SessionInterface;

class StorableSession implements StorableSessionInterface
{
    /** @var SessionIdInterface */
    private $id;
    /** @var SessionInterface */
    private $wrappedSession;
    /** @var \PSR7Sessions\Storage\Adapter\StorageInterface */
    private $storage;

    public static function create(StorageInterface $storage):StorableSession
    {
        return new self(DefaultSessionData::newEmptySession(), $storage);
    }

    /**
     * @internal Should only be called by a storage
     */
    public static function fromId(
        SessionInterface $wrappedSession,
        StorageInterface $storage,
        SessionIdInterface $id
    ):StorableSession
    {
        $session = new self($wrappedSession, $storage);
        $session->id = $id;
        return $session;
    }

    public function __construct(SessionInterface $wrappedSession, StorageInterface $storage)
    {
        $this->id = new UuidSessionId;
        $this->wrappedSession = $wrappedSession;
        $this->storage = $storage;
    }

    /**
     * {@inheritdoc}
     */
    public function getId() : SessionIdInterface
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value)
    {
        $this->wrappedSession->set($key, $value);
        $this->save();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        return $this->wrappedSession->get($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key)
    {
        $this->wrappedSession->remove($key);
        $this->save();
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->wrappedSession->clear();
        $this->save();
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key) : bool
    {
        return $this->wrappedSession->has($key);
    }

    /**
     * {@inheritdoc}
     */
    public function hasChanged() : bool
    {
        return $this->wrappedSession->hasChanged();
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty() : bool
    {
        return $this->wrappedSession->isEmpty();
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->wrappedSession->jsonSerialize();
    }

    private function save()
    {
        $this->storage->save($this);
    }
}
