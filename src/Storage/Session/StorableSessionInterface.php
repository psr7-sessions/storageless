<?php

namespace PSR7Sessions\Storage\Session;

use PSR7Sessions\Storage\Id\SessionIdInterface;
use PSR7Sessions\Storageless\Session\SessionInterface;

interface StorableSessionInterface extends SessionInterface
{
    public function getId() : SessionIdInterface;
}
