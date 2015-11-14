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

    public function testContainerIsEmptyWhenDataIsRemovedFromIt()
    {
        $data = Data::fromTokenData(['foo' => 'bar']);

        $data->remove('foo');

        self::assertTrue($data->isEmpty());
    }

    /**
     * @dataProvider storageScalarDataProvider
     */
    public function testContainerDataIsStoredAndRetrieved(string $key, $value)
    {
        $data = Data::newEmptySession();

        $data->set($key, $value);
        self::assertSame($value, $data->get($key));
    }

    /**
     * @dataProvider storageScalarDataProvider
     */
    public function testSettingDataInAContainerMarksTheContainerAsMutated(string $key, $value)
    {
        $data = Data::newEmptySession();

        $data->set($key, $value);

        self::assertTrue($data->hasChanged());
    }

    /**
     * @dataProvider storageScalarDataProvider
     */
    public function testContainerBuiltWithDataContainsData(string $key, $value)
    {
        $data = Data::fromTokenData([$key => $value]);

        self::assertSame($value, $data->get($key));
    }

    /**
     * @dataProvider storageScalarDataProvider
     */
    public function testContainerBuiltWithStdClassContainsData(string $key, $value)
    {
        if ("\0" === $key || "\0" === $value || '' === $key) {
            $this->markTestIncomplete('Null bytes or empty keys are not supported by PHP\'s stdClass');
        }

        $data = Data::fromDecodedTokenData((object) [$key => $value]);

        self::assertSame($value, $data->get($key));
    }

    public function testContainerStoresJsonSerializableData()
    {
        $object = new class implements \JsonSerializable
        {
            public function jsonSerialize()
            {
                return ['foo' => 'bar'];
            }
        };

        $data = Data::fromTokenData(['key' => $object]);

        $this->assertSame(['foo' => 'bar'], $data->get('key'));
    }

    public function testContainerStoresPublicPropertiesData()
    {
        $object = new class
        {
            public $foo = 'bar';
            public $baz = 'tab';
        };

        $data = Data::fromTokenData(['key' => $object]);

        $this->assertSame(['foo' => 'bar', 'baz' => 'tab'], $data->get('key'));
    }

    /**
     * @dataProvider storageNonScalarDataProvider
     */
    public function testContainerStoresScalarValueFromNestedObjects($nonScalar, $expectedScalar)
    {
        $data = Data::fromTokenData(['key' => $nonScalar]);

        self::assertSame($expectedScalar, $data->get('key'));

        $data->set('otherKey', $nonScalar);

        self::assertSame($expectedScalar, $data->get('otherKey'));
    }

    public function storageNonScalarDataProvider() : array
    {
        return [
            'class' => [
                new class
                {
                    public $foo = 'bar';
                },
                ['foo' => 'bar'],
            ],
            'object' => [
                (object) ['baz' => [(object) ['tab' => 'taz']]],
                ['baz' => ['tab' => 'taz']],
            ],
            'array'  => [
                [(object) ['tar' => 'tan']],
                ['tar' => 'tan'],
            ],
            'jsonSerializable' => [
                new class implements \JsonSerializable
                {
                    public function jsonSerialize()
                    {
                        return (object) ['war' => 'zip'];
                    }
                },
                ['war' => 'zip'],
            ],
            'emptyObject' => [
                new \stdClass(),
                [],
            ],
        ];
    }

    public function storageScalarDataProvider() : array
    {
        return [
            'string'             => ['foo', 'bar'],
            'empty string'       => ['foo', ''],
            'null-ish string'    => ['foo', 'null'],
            'null'               => ['foo', null],
            'empty string key'   => ['', 'bar'],
            '0-ish string'       => ['foo', '0'],
            'empty array string' => ['foo', '[]'],
            'null byte'          => ['foo', "\0"],
            'null byte key'      => ["\0", 'bar'],
            'zero'               => ['foo', 0],
            'integer'            => ['foo', 1],
            'negative integer'   => ['foo', -1],
            'large integer'      => ['foo', PHP_INT_MAX],
            'small integer'      => ['foo', PHP_INT_MIN],
            'float'              => ['foo', 0.1],
            'float zero'         => ['foo', 0.0],
            'empty array'        => ['foo', []],
            '0-indexed-array'    => ['foo', ['bar', 'baz']],
            'map'                => ['foo', ['bar' => 'baz']],
            'nested array'       => ['foo', ['bar' => []]],
        ];
    }
}
