<?php

namespace PSR7Session\Id;

class SessionId implements SessionIdInterface
{
    /** @var string */
    private $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function __toString() : string
    {
        return $this->id;
    }
}
