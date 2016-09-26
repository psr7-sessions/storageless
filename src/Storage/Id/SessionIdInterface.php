<?php

namespace PSR7Sessions\Storage\Id;

interface SessionIdInterface
{
    public function __toString() : string;
}
