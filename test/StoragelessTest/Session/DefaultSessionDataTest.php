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

namespace PSR7SessionsTest\Storageless\Session;

use PHPUnit_Framework_TestCase;
use PSR7Sessions\Storageless\Session\DefaultSessionData;

/**
 * @covers \PSR7Sessions\Storageless\Session\DefaultSessionData
 */
final class DefaultSessionDataTest extends PHPUnit_Framework_TestCase
{
    public function testFromFromTokenDataBuildsADataContainer()
    {
        self::assertInstanceOf(DefaultSessionData::class, DefaultSessionData::fromTokenData([]));
    }

    public function testNewEmptySessionProducesAContainer()
    {
        self::assertInstanceOf(DefaultSessionData::class, DefaultSessionData::newEmptySession());
    }

    public function testContainerIsEmptyWhenCreatedExplicitlyAsEmpty()
    {
        self::assertTrue(DefaultSessionData::newEmptySession()->isEmpty());
    }

    public function testContainerIsEmptyWhenCreatedWithoutData()
    {
        self::assertTrue(DefaultSessionData::fromTokenData([])->isEmpty());
    }

    public function testContainerIsNotEmptyWhenDataIsProvided()
    {
        self::assertFalse(DefaultSessionData::fromTokenData(['foo' => 'bar'])->isEmpty());
    }

    public function testContainerIsNotEmptyWhenDataIsPassedToItAfterwards()
    {
        $session = DefaultSessionData::newEmptySession();

        $session->set('foo', 'bar');

        self::assertFalse($session->isEmpty());
    }

    public function testContainerIsEmptyWhenDataIsRemovedFromIt()
    {
        $session = DefaultSessionData::fromTokenData(['foo' => 'bar']);

        $session->remove('foo');

        self::assertTrue($session->isEmpty());
    }

    public function testClearWillRemoveEverythingFromTheSessionContainer()
    {
        $session = DefaultSessionData::fromTokenData([
            'foo' => 'bar',
            'baz' => 'tab',
        ]);

        $session->clear();

        self::assertTrue($session->isEmpty());
        self::assertTrue($session->hasChanged());
        self::assertFalse($session->has('foo'));
        self::assertFalse($session->has('baz'));
    }

    /**
     * @dataProvider storageScalarDataProvider
     */
    public function testContainerDataIsStoredAndRetrieved(string $key, $value)
    {
        $session = DefaultSessionData::newEmptySession();

        $session->set($key, $value);
        self::assertSame($value, $session->get($key));
    }

    /**
     * @dataProvider storageScalarDataProvider
     */
    public function testSettingDataInAContainerMarksTheContainerAsMutated(string $key, $value)
    {
        $session = DefaultSessionData::newEmptySession();

        $session->set($key, $value);

        self::assertTrue($session->hasChanged());
    }

    /**
     * @dataProvider storageScalarDataProvider
     */
    public function testContainerIsNotChangedWhenScalarDataIsSetAndOverwrittenInIt(string $key, $value)
    {
        $session = DefaultSessionData::fromTokenData([$key => $value]);

        self::assertFalse($session->hasChanged());

        $session->set($key, $value);

        self::assertFalse($session->hasChanged());
    }

    /**
     * @dataProvider storageNonScalarDataProvider
     */
    public function testContainerIsNotChangedWhenNonScalarDataIsSetAndOverwrittenInIt($nonScalarValue)
    {
        $session = DefaultSessionData::fromTokenData(['key' => $nonScalarValue]);

        self::assertFalse($session->hasChanged());

        $session->set('key', $nonScalarValue);

        self::assertFalse($session->hasChanged());
    }

    /**
     * @dataProvider storageScalarDataProvider
     */
    public function testContainerBuiltWithDataContainsData(string $key, $value)
    {
        $session = DefaultSessionData::fromTokenData([$key => $value]);

        self::assertTrue($session->has($key));
        self::assertSame($value, $session->get($key));
    }

    /**
     * @dataProvider storageScalarDataProvider
     */
    public function testContainerBuiltWithStdClassContainsData(string $key, $value)
    {
        if ("\0" === $key || "\0" === $value || '' === $key) {
            self::markTestSkipped('Null bytes or empty keys are not supported by PHP\'s stdClass');
        }

        $session = DefaultSessionData::fromDecodedTokenData((object) [$key => $value]);

        self::assertTrue($session->has($key));
        self::assertSame($value, $session->get($key));
    }

    /**
     * @dataProvider storageNonScalarDataProvider
     */
    public function testContainerStoresScalarValueFromNestedObjects($nonScalar, $expectedScalar)
    {
        $session = DefaultSessionData::fromTokenData(['key' => $nonScalar]);

        self::assertSame($expectedScalar, $session->get('key'));

        $session->set('otherKey', $nonScalar);

        self::assertSame($expectedScalar, $session->get('otherKey'));
    }

    /**
     * @dataProvider storageScalarDataProvider
     */
    public function testGetWillReturnDefaultValueOnNonExistingKey(string $key, $value)
    {
        $session = DefaultSessionData::newEmptySession();

        self::assertFalse($session->has($key));
        self::assertSame($value, $session->get($key, $value));
    }

    /**
     * @dataProvider storageNonScalarDataProvider
     */
    public function testGetWillReturnScalarCastDefaultValueOnNonExistingKey($nonScalar, $expectedScalar)
    {
        self::assertSame($expectedScalar, DefaultSessionData::newEmptySession()->get('key', $nonScalar));
    }

    public function testAllMethodsAreCoveredByAnInterfacedMethod()
    {
        $reflection = new \ReflectionClass(DefaultSessionData::class);
        $interfaces = $reflection->getInterfaces();

        foreach ($reflection->getMethods() as $method) {
            if ($method->isConstructor() || $method->isStatic() || ! $method->isPublic()) {
                continue;
            }

            self::assertNotEmpty(array_filter(
                $interfaces,
                function (\ReflectionClass $interface) use ($method) {
                    return $interface->hasMethod($method->getName());
                }
            ), $method->getName());
        }
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
                ['baz' => [['tab' => 'taz']]],
            ],
            'array'  => [
                [(object) ['tar' => 'tan']],
                [['tar' => 'tan']],
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
