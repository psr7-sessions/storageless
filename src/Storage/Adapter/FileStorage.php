<?php

declare(strict_types = 1);

namespace PSR7Sessions\Storage\Adapter;

use PSR7Sessions\Storage\Id\SessionIdInterface;
use PSR7Sessions\Storage\Session\StorableSession;
use PSR7Sessions\Storage\Session\StorableSessionInterface;
use PSR7Sessions\Storageless\Session\DefaultSessionData;

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
