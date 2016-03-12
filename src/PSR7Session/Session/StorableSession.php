<?php

namespace PSR7Session\Session;

use PSR7Session\Id\SessionIdInterface;
use PSR7Session\Id\UuidSessionId;
use PSR7Session\Storage\StorageInterface;

class StorableSession implements StorableSessionInterface
{
    /** @var SessionIdInterface */
    private $id;
    /** @var SessionInterface */
    private $wrappedSession;
    /** @var StorageInterface */
    private $storage;

    public static function create(
        SessionInterface $wrappedSession,
        StorageInterface $storage
    ) : StorableSessionInterface
    {
        return new self($wrappedSession, $storage, new UuidSessionId());
    }

    public static function fromStorage(StorageInterface $storage, SessionIdInterface $id) : StorableSessionInterface
    {
        return new self($storage->load($id), $storage, $id);
    }

    private function __construct(SessionInterface $wrappedSession, StorageInterface $storage, SessionIdInterface $id)
    {
        $this->wrappedSession = $wrappedSession;
        $this->storage = $storage;
        $this->id = $id;
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
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->wrappedSession->clear();
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
}
