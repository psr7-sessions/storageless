<?php

namespace PSR7SessionTest\Id;

use PHPUnit_Framework_TestCase;
use PSR7Session\Id\SessionId;

class SessionIdTest extends PHPUnit_Framework_TestCase
{
    public function testToString()
    {
        $id = new SessionId('my-id');

        $this->assertSame('my-id', (string)$id);
    }
}
