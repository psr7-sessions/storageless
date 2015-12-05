<?php

namespace PSR7Session\Id\Factory;

use PSR7Session\Id\SessionId;
use PSR7Session\Id\SessionIdInterface;

class SessionIdFactory implements SessionIdFactoryInterface
{
    public function create() : SessionIdInterface
    {
        // TODO Make this secure
        return new SessionId(uniqid());
    }
}
