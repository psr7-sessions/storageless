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

namespace StoragelessSessionTest\Session;

use PHPUnit_Framework_TestCase;
use StoragelessSession\Session\Data;

/**
 * Tests for {@see StoragelessSession\Session\Data}.
 *
 * @group   Coverage
 * @covers  \StoragelessSession\Session\Data
 * @author  Jefersson Nathan <malukenho@phpse.net>
 *
 * @license MIT
 */
final class DataTest extends PHPUnit_Framework_TestCase
{
    public function testCannotInstantiateDataDirectly()
    {
        self::setExpectedException(\Error::class);

        new Data([], []);
    }

    public function testCanCreateScopeIfNotExists()
    {
        $data = Data::newEmptySession();

        self::assertAttributeEmpty('scopes', $data);

        $scope = $data->getScope('foo');

        self::assertAttributeEquals($data->jsonSerialize(), 'scopes', $data);
        self::assertAttributeCount(1, 'scopes', $data);

        self::assertAttributeEmpty('data', $scope);
    }

    /**
     * @dataProvider getDataWithInvalidScopeDataType
     *
     * @param array $wrongData
     */
    public function testCanCreateScopeWhenDataValueIsNotAnArray(array $wrongData)
    {
        $data = Data::fromTokenData($wrongData, []);

        self::assertAttributeEmpty('scopes', $data);
        self::assertAttributeCount(0, 'scopes', $data);

        $scope = $data->getScope('foo');

        self::assertAttributeNotEmpty('scopes', $data);
        self::assertAttributeCount(1, 'scopes', $data);

        self::assertSame($wrongData, $scope->jsonSerialize());
    }

    public function testCanCreateScopeWhenDatIsAnArray()
    {
        $expected = [
            'foo' => 'bar',
        ];

        $param = [
            'foo' => [
                'foo' => 'bar',
            ],
        ];

        $data = Data::fromTokenData($param, []);

        self::assertAttributeEmpty('scopes', $data);
        self::assertAttributeCount(0, 'scopes', $data);

        $scope = $data->getScope('foo');

        self::assertAttributeNotEmpty('scopes', $data);
        self::assertAttributeCount(1, 'scopes', $data);

        self::assertSame($expected, $scope->jsonSerialize());
    }

    public function testCanCreateDataByDecodedTokenData()
    {
        $object = (object) [
            'foo' => 'bar',
        ];

        $data = Data::fromDecodedTokenData($object);

        self::assertAttributeNotEmpty('data', $data);
        self::assertAttributeCount(1, 'data', $data);

        $scope = $data->getScope('foo');
        self::assertSame('bar', $scope->get('foo'));
    }

    public function testIsEmptyDataContainer()
    {
        $container = Data::newEmptySession();
        self::assertTrue($container->isEmpty());

        $scope = $container->getScope('name');
        $scope->set('foo', 'bar');

        self::assertFalse($container->isEmpty());
    }

    public function testVerifyWhenDataIsModified()
    {
        $container = Data::fromTokenData(['foo' => 'bar'], []);
        self::assertFalse($container->isModified());

        $scope = $container->getScope('foo');
        $scope->set('foo', 'baz');

        self::assertTrue($scope->isModified());
        self::assertTrue($container->isModified());
    }

    public function getDataWithInvalidScopeDataType()
    {
        return [
            [
                [
                    'foo' => 'string',
                ],
            ],
            [
                [
                    'foo' => 124,
                ],
            ],
            [
                [
                    'foo' => new \stdClass(),
                ],
            ],
            [
                [
                    'foo' => 0x123,
                ],
            ],
            [
                [
                    'foo' => 123.123,
                ],
            ],
        ];
    }
}
