<?php

namespace PSR7Session\Id\Factory;

use PSR7Session\Id\SessionIdInterface;

interface SessionIdFactoryInterface
{
    public function create() : SessionIdInterface;
}
