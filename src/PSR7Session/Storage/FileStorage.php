<?php

declare(strict_types = 1);

namespace PSR7Session\Storage;

use PSR7Session\Id\SessionIdInterface;
use PSR7Session\Session\DefaultSessionData;
use PSR7Session\Session\StorableSession;
use PSR7Session\Session\StorableSessionInterface;

class FileStorage implements StorageInterface
{
    /** @var string */
    private $directory;

    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    public function save(StorableSessionInterface $session)
    {
        file_put_contents($this->buildPath($session->getId()), json_encode($session));
    }

    public function load(SessionIdInterface $id):StorableSessionInterface
    {
        $path = $this->buildPath($id);
        if (!file_exists($path)) {
            return new StorableSession(DefaultSessionData::newEmptySession(), $this);
        }
        $json = file_get_contents($path);
        return StorableSession::fromId(DefaultSessionData::fromTokenData(json_decode($json, true)), $this, $id);
    }

    public function destroy(SessionIdInterface $id)
    {
        unlink($this->buildPath($id));
    }

    private function buildPath(SessionIdInterface $id) : string
    {
        return $this->directory . '/' . $id;
    }
}
