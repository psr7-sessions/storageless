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
use StoragelessSession\Session\SessionScope;

/**
 * Tests for {@see StoragelessSession\Session\SessionScope}.
 *
 * @group  Coverage
 * @covers \StoragelessSession\Session\SessionScope
 * @author Jefersson Nathan <malukenho@phpse.net>
 *
 * @license MIT
 */
final class SessionScopeTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideDataTypes
     */
    public function testCanStoreAndRetrieveDataOnScope(string $key, $expected)
    {
        $session = SessionScope::fromArrayAndExpiration([]);
        $session->set($key, $expected);

        self::assertSame($expected, $session->get($key));
    }

    public function testStateIsChangedWhenSetANewDataToScope()
    {
        $session = SessionScope::fromArrayAndExpiration(['foo' => '123']);
        self::assertFalse($session->isModified());

        $session->set('bar', 'baz');
        self::assertTrue($session->isModified());
    }

    public function testStateIsChangedWhenRemoveDataFromScope()
    {
        $session = SessionScope::fromArrayAndExpiration(['foo' => '123']);
        self::assertFalse($session->isModified());

        $session->remove('foo');
        self::assertTrue($session->isModified());
    }

    public function testScopeIsEmpty()
    {
        $session = SessionScope::fromArrayAndExpiration([]);
        self::assertTrue($session->isEmpty());

        $session->set('foo', 'boo');
        self::assertFalse($session->isEmpty());
        self::assertTrue($session->isModified());
    }

    public function provideDataTypes()
    {
        return [
            ['foo', 'string'],
            ['foo', ['foo' => 'string']],
            ['foo#!@', new \stdClass()],
            ['foo123', []],
            ['foo bar', ''],
            ['foo-bar', null],
            ['foo_bar', 123],
            ['foo.bar', 123.2],
            ['foo()bar', 0x12],
        ];
    }
}
