<?php

namespace PSR7Sessions\Storage\Id;

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
