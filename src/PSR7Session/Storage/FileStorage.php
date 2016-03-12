<?php

declare(strict_types = 1);

namespace PSR7Session\Storage;

use PSR7Session\Id\Factory\SessionIdFactoryInterface;
use PSR7Session\Id\SessionIdInterface;
use PSR7Session\Session\DefaultSessionData;
use PSR7Session\Session\SessionInterface;
use PSR7Session\Session\StorableSessionInterface;

class FileStorage implements StorageInterface
{
    /** @var string */
    private $directory;
    /** @var SessionIdFactoryInterface */
    private $idFactory;

    public function __construct(string $directory, SessionIdFactoryInterface $idFactory)
    {
        $this->directory = $directory;
        $this->idFactory = $idFactory;
    }

    public function save(StorableSessionInterface $session)
    {
        file_put_contents($this->buildPath($session->getId()), json_encode($session));
    }

    /**
     * @return SessionInterface|null
     */
    public function load(SessionIdInterface $id)
    {
        $path = $this->buildPath($id);
        if (!file_exists($path)) {
            return null;
        }
        $json = file_get_contents($path);
        return DefaultSessionData::fromTokenData(json_decode($json, true));
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
