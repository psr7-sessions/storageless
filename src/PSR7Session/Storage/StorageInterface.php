<?php

namespace PSR7Session\Storage;

use PSR7Session\Id\SessionIdInterface;
use PSR7Session\Session\SessionInterface;
use PSR7Session\Session\StorableSessionInterface;

interface StorageInterface
{
    public function save(StorableSessionInterface $session);

    /**
     * @return SessionInterface|null
     */
    public function load(SessionIdInterface $id);

    public function destroy(SessionIdInterface $id);
}
