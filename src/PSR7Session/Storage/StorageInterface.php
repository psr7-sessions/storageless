<?php

namespace PSR7Session\Storage;

use PSR7Session\Id\SessionIdInterface;
use PSR7Session\Session\SessionInterface;

interface StorageInterface
{
    public function save(SessionInterface $session);

    public function load(SessionIdInterface $id) : SessionInterface;

    public function destroy(SessionIdInterface $id);
}
