<?php

namespace PSR7Session\Id;

interface SessionIdInterface
{
    public function __toString() : string;
}
