<?php

namespace PSR7Sessions\Storage\Id;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class UuidSessionId implements SessionIdInterface
{
    /** @var UuidInterface */
    private $uuid;

    public function __construct()
    {
        $this->uuid = Uuid::uuid4();
    }

    public function __toString() : string
    {
        return (string)$this->uuid;
    }
}
