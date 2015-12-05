<?php

namespace PSR7Session\Session;

use PSR7Session\Id\SessionIdInterface;

interface StorableSessionInterface extends SessionInterface
{
    /**
     * @return SessionIdInterface|null
     */
    public function getId();

    public function setId(SessionIdInterface $id);
}
