<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

declare(strict_types=1);

namespace StoragelessSessionTest\Session;

use PHPUnit_Framework_TestCase;
use StoragelessSession\Session\Data;

/**
 * @covers \StoragelessSession\Session\Data
 */
final class DataTest extends PHPUnit_Framework_TestCase
{
    public function testFromFromTokenDataBuildsADataContainer()
    {
        self::assertInstanceOf(Data::class, Data::fromTokenData([]));
    }

    public function testNewEmptySessionProducesAContainer()
    {
        self::assertInstanceOf(Data::class, Data::newEmptySession());
    }

    public function testContainerIsEmptyWhenCreatedExplicitlyAsEmpty()
    {
        self::assertTrue(Data::newEmptySession()->isEmpty());
    }

    public function testContainerIsEmptyWhenCreatedWithoutData()
    {
        self::assertTrue(Data::fromTokenData([])->isEmpty());
    }

    public function testContainerIsNotEmptyWhenDataIsProvided()
    {
        self::assertFalse(Data::fromTokenData(['foo' => 'bar'])->isEmpty());
    }

    public function testContainerIsNotEmptyWhenDataIsPassedToItAfterwards()
    {
        $data = Data::newEmptySession();

        $data->set('foo', 'bar');

        self::assertFalse($data->isEmpty());
    }
}
