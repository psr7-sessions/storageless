<?php

namespace PSR7SessionTest\Id\Factory;

use PHPUnit_Framework_TestCase;
use PSR7Session\Id\Factory\SessionIdFactory;
use PSR7Session\Id\SessionIdInterface;

class SessionIdFactoryTest extends PHPUnit_Framework_TestCase
{
    /** @var SessionIdFactory */
    private $factory;

    public function setUp()
    {
        $this->factory = new SessionIdFactory();
    }

    public function testReturnType()
    {
        $id = $this->factory->create();

        $this->assertInstanceOf(SessionIdInterface::class, $id);
    }
}
