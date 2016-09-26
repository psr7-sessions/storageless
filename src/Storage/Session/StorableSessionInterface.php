<?php

namespace PSR7Session\Session;

use PSR7Session\Id\SessionIdInterface;

interface StorableSessionInterface extends SessionInterface
{
    public function getId() : SessionIdInterface;
}
