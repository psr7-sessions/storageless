<?php

namespace PSR7Session\Session;

use PSR7Session\Id\SessionIdInterface;

class StorableSession implements StorableSessionInterface
{
    /** @var SessionIdInterface|null */
    private $id;
    /** @var SessionInterface */
    private $wrappedSession;

    public function __construct(SessionInterface $wrappedSession)
    {
        $this->wrappedSession = $wrappedSession;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    public function setId(SessionIdInterface $id)
    {
        $this->id = $id;
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
        $this->wrappedSession->get($key, $default);
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
