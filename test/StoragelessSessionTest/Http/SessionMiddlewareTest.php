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

namespace StoragelessSessionTest\Http;

use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Token;
use PHPUnit_Framework_TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use StoragelessSession\Http\SessionMiddleware;
use StoragelessSession\Session\Data;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

final class SessionMiddlewareTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider validMiddlewaresProvider
     */
    public function testInjectsSessionDataEvenWithNoNextMiddleware(SessionMiddleware $middleware)
    {
        $containerValue = uniqid('', true);

        $containerPopulationMiddleware = $this
            ->buildFakeMiddleware(function (ServerRequestInterface $request) use ($containerValue) {
                /* @var $data Data */
                $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

                $scope = $data->getScope('foo');
                $scope->set('foo', $containerValue);

                return true;
            });

        // populate the cookies
        $firstResponse = $middleware(new ServerRequest(), new Response(), $containerPopulationMiddleware);

        $response = $middleware(
            (new ServerRequest())
                ->withCookieParams([
                    SessionMiddleware::DEFAULT_COOKIE
                        => FigResponseCookies::get($firstResponse, SessionMiddleware::DEFAULT_COOKIE)->getValue(),
                ]),
            $firstResponse
        );

        self::assertNotEmpty(FigResponseCookies::get($response, SessionMiddleware::DEFAULT_COOKIE)->getValue());
    }

    /**
     * @dataProvider validMiddlewaresProvider
     */
    public function testWillIgnoreRequestsWithExpiredTokens(SessionMiddleware $middleware)
    {
        $expiredTokenRequest = (new ServerRequest())
            ->withCookieParams([
                SessionMiddleware::DEFAULT_COOKIE => (string) (new Builder())
                    ->setIssuedAt((new \DateTime('-1 day'))->getTimestamp())
                    ->setExpiration((new \DateTime('-2 day'))->getTimestamp())
                    ->set(SessionMiddleware::SESSION_CLAIM, Data::fromTokenData(['foo' => 'bar'], []))
                    ->sign($this->getSigner($middleware), $this->getSignatureKey($middleware))
                    ->getToken()
            ]);

        $checkingMiddleware = $this->buildFakeMiddleware(function (ServerRequestInterface $request) {
            /* @var $data Data */
            $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

            self::assertTrue($data->isEmpty());

            return true;
        });

        $middleware($expiredTokenRequest, new Response(), $checkingMiddleware);
    }

    /**
     * @dataProvider validMiddlewaresProvider
     */
    public function testWillIgnoreUnSignedTokens(SessionMiddleware $middleware)
    {
        $unsignedToken = (new ServerRequest())
            ->withCookieParams([
                SessionMiddleware::DEFAULT_COOKIE => (string) (new Builder())
                    ->setIssuedAt((new \DateTime())->getTimestamp())
                    ->setExpiration((new \DateTime())->getTimestamp())
                    ->set(SessionMiddleware::SESSION_CLAIM, Data::fromTokenData(['foo' => 'bar'], []))
                    ->getToken(),
            ]);

        $checkingMiddleware = $this->buildFakeMiddleware(function (ServerRequestInterface $request) {
            /* @var $data Data */
            $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

            self::assertTrue($data->isEmpty());

            return true;
        });

        $middleware($unsignedToken, new Response(), $checkingMiddleware);
    }

    /**
     * @dataProvider validMiddlewaresProvider
     */
    public function testWillIgnoreMalformedTokens(SessionMiddleware $middleware)
    {
        $checkingMiddleware = $this->buildFakeMiddleware(function (ServerRequestInterface $request) {
            /* @var $data Data */
            $data  = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
            $scope = $data->getScope('foo');

            self::assertTrue($scope->isEmpty());

            return true;
        });

        $middleware(
            (new ServerRequest())->withCookieParams([SessionMiddleware::DEFAULT_COOKIE => 'malformed content']),
            new Response(),
            $checkingMiddleware
        );
    }

    public function testAllowsModifyingHeaderDetails()
    {
        $middleware = new SessionMiddleware(
            new Sha256(),
            'foo',
            'foo',
            SetCookie::create('a-different-cookie-name')
                ->withDomain('foo.bar')
                ->withPath('/yadda')
                ->withHttpOnly(false)
                ->withMaxAge(123123)
                ->withValue('a-random-value'),
            new Parser(),
            123456
        );

        $containerPopulationMiddleware = $this
            ->buildFakeMiddleware(function (ServerRequestInterface $request) {
                /* @var $data Data */
                $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

                $scope = $data->getScope('foo');
                $scope->set('foo', 'bar');

                return true;
            });

        $response = $middleware(new ServerRequest(), new Response(), $containerPopulationMiddleware);

        self::assertNull(FigResponseCookies::get($response, SessionMiddleware::DEFAULT_COOKIE)->getValue());

        $tokenCookie = FigResponseCookies::get($response, 'a-different-cookie-name');

        self::assertNotEmpty($tokenCookie->getValue());
        self::assertNotSame('a-random-value', $tokenCookie->getValue());
        self::assertSame('foo.bar', $tokenCookie->getDomain());
        self::assertSame('/yadda', $tokenCookie->getPath());
        self::assertFalse($tokenCookie->getHttpOnly());
        self::assertEquals(123123, $tokenCookie->getMaxAge());
        self::assertEquals(time() + 123456, $tokenCookie->getExpires(), '', 2);
    }

    /**
     * @dataProvider validMiddlewaresProvider
     */
    public function testSkipsInjectingSessionCookieOnEmptyContainer(SessionMiddleware $middleware)
    {
        $response = $middleware(new ServerRequest(), new Response());

        self::assertNull(FigResponseCookies::get($response, SessionMiddleware::DEFAULT_COOKIE)->getValue());
    }

    public function testRemovesSessionCookieOnEmptySessionContainer()
    {
        self::markTestIncomplete('This feature is yet to be implemented, and we may do so in a different middleware');
    }

    /**
     * @dataProvider validMiddlewaresProvider
     */
    public function testExtractsSessionContainerFromEmptyRequest(SessionMiddleware $middleware)
    {
        $checkingMiddleware = $this->buildFakeMiddleware(function (ServerRequestInterface $request) {
            self::assertInstanceOf(Data::class, $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE));

            return true;
        });

        self::assertInstanceOf(
            ResponseInterface::class,
            $middleware(new ServerRequest(), new Response(), $checkingMiddleware)
        );
    }

    /**
     * @dataProvider validMiddlewaresProvider
     */
    public function testInjectsSessionInResponseCookies(SessionMiddleware $middleware)
    {
        $checkingMiddleware = $this->buildFakeMiddleware(function (ServerRequestInterface $request) {
            /* @var $data Data */
            $data  = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
            $scope = $data->getScope('foo');

            $scope->set('foo', 'bar');

            return new Response();
        });

        $response = $middleware(new ServerRequest(), new Response(), $checkingMiddleware);

        self::assertEmpty(FigResponseCookies::get($response, 'non-existing')->getValue());
        self::assertInstanceOf(
            Token::class,
            (new Parser())->parse(FigResponseCookies::get($response, SessionMiddleware::DEFAULT_COOKIE)->getValue())
        );
    }

    /**
     * @dataProvider validMiddlewaresProvider
     */
    public function testSessionContainerCanBeReusedOverMultipleRequests(SessionMiddleware $middleware)
    {
        $containerValue = uniqid('', true);

        $containerPopulationMiddleware = $this
            ->buildFakeMiddleware(function (ServerRequestInterface $request) use ($containerValue) {
                /* @var $data Data */
                $data  = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                $scope = $data->getScope('foo');

                $scope->set('foo', $containerValue);

                return true;
            });

        $containerCheckingMiddleware = $this
            ->buildFakeMiddleware(function (ServerRequestInterface $request) use ($containerValue) {
                /* @var $data Data */
                $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                $scope = $data->getScope('foo');

                self::assertSame($containerValue, $scope->get('foo'));

                return true;
            });

        $response = $middleware(new ServerRequest(), new Response(), $containerPopulationMiddleware);

        $middleware(
            (new ServerRequest())
                ->withCookieParams([
                    SessionMiddleware::DEFAULT_COOKIE
                        => FigResponseCookies::get($response, SessionMiddleware::DEFAULT_COOKIE)->getValue(),
                ]),
            new Response(),
            $containerCheckingMiddleware
        );
    }

    public function testRejectsTokensWithInvalidSignature()
    {
        $middleware = new SessionMiddleware(
            new Sha256(),
            'foo',
            'bar', // wrong symmetric key (on purpose)
            SetCookie::create(SessionMiddleware::DEFAULT_COOKIE),
            new Parser(),
            100
        );

        $containerPopulationMiddleware = $this
            ->buildFakeMiddleware(function (ServerRequestInterface $request) {
                /* @var $data Data */
                $data  = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                $scope = $data->getScope('foo');

                $scope->set('someproperty', 'someValue');

                return true;
            });

        $containerCheckingMiddleware = $this
            ->buildFakeMiddleware(function (ServerRequestInterface $request) {
                /* @var $data Data */
                $data  = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                $scope = $data->getScope('foo');

                self::assertNull($scope->get('someValue'));

                return true;
            });

        $response = $middleware(new ServerRequest(), new Response(), $containerPopulationMiddleware);

        $middleware(
            (new ServerRequest())
                ->withCookieParams([
                    SessionMiddleware::DEFAULT_COOKIE
                        => FigResponseCookies::get($response, SessionMiddleware::DEFAULT_COOKIE)->getValue(),
                ]),
            new Response(),
            $containerCheckingMiddleware
        );
    }

    /**
     * @return SessionMiddleware[][]
     */
    public function validMiddlewaresProvider()
    {
        return [
            [new SessionMiddleware(
                new Sha256(),
                'foo',
                'foo',
                SetCookie::create(SessionMiddleware::DEFAULT_COOKIE),
                new Parser(),
                100
            )],
            [SessionMiddleware::fromSymmetricKeyDefaults('not relevant', 100)],
            [SessionMiddleware::fromAsymmetricKeyDefaults(
                file_get_contents(__DIR__ . '/../../keys/private_key.pem'),
                file_get_contents(__DIR__ . '/../../keys/public_key.pem'),
                200
            )],
        ];
    }

    /**
     * @param callable               $callback
     * @param ResponseInterface|null $returnedResponse
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|callable
     */
    private function buildFakeMiddleware(callable $callback, ResponseInterface $returnedResponse = null)
    {
        $middleware = $this->getMock(\stdClass::class, ['__invoke']);

        $middleware
            ->expects(self::once())
            ->method('__invoke')
            ->with(self::callback($callback))
            ->willReturn($returnedResponse ?? new Response());

        return $middleware;
    }

    /**
     * @param SessionMiddleware $middleware
     *
     * @return Signer
     */
    private function getSigner(SessionMiddleware $middleware)
    {
        $signerReflection = new \ReflectionProperty(SessionMiddleware::class, 'signer');

        $signerReflection->setAccessible(true);

        return $signerReflection->getValue($middleware);
    }

    /**
     * @param SessionMiddleware $middleware
     *
     * @return string
     */
    private function getSignatureKey(SessionMiddleware $middleware)
    {
        $signatureKeyReflection = new \ReflectionProperty(SessionMiddleware::class, 'signatureKey');

        $signatureKeyReflection->setAccessible(true);

        return $signatureKeyReflection->getValue($middleware);
    }
}
