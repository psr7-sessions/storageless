<?php

namespace PSR7Session\Storage;

use PSR7Session\Id\SessionIdInterface;
use PSR7Session\Session\DefaultSessionData;
use PSR7Session\Session\StorableSession;
use PSR7Session\Session\StorableSessionInterface;

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
