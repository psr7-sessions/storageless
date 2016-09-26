<?php

namespace PSR7Sessions\Storage\Adapter;

use PSR7Sessions\Storage\Id\SessionIdInterface;
use PSR7Sessions\Storage\Session\StorableSession;
use PSR7Sessions\Storage\Session\StorableSessionInterface;
use PSR7Sessions\Storageless\Session\DefaultSessionData;

class MemoryStorage implements StorageInterface
{
    /** @var array */
    private $sessions = [];

    public function save(StorableSessionInterface $session)
    {
        $this->sessions[(string)$session->getId()] = $session->jsonSerialize();
    }

    public function load(SessionIdInterface $id):StorableSessionInterface
    {
        $stringId = (string)$id;
        if (!$this->has($stringId)) {
            return StorableSession::fromId(DefaultSessionData::newEmptySession(), $this, $id);
        }
        $wrappedSession = DefaultSessionData::fromTokenData($this->sessions[$stringId]);
        return new StorableSession($wrappedSession, $this);
    }

    public function destroy(SessionIdInterface $id)
    {
        $stringId = (string)$id;
        if (!$this->has($stringId)) {
            return;
        }
        unset($this->sessions[$stringId]);
    }

    private function has(string $id):bool
    {
        return array_key_exists($id, $this->sessions);
    }
}
