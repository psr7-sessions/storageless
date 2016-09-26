<?php

namespace PSR7Sessions\Storage\Adapter;

use PSR7Sessions\Storage\Id\SessionIdInterface;
use PSR7Sessions\Storage\Session\StorableSessionInterface;

interface StorageInterface
{
    public function save(StorableSessionInterface $session);

    public function load(SessionIdInterface $id):StorableSessionInterface;

    public function destroy(SessionIdInterface $id);
}
