<?php
namespace StoragelessSessionTest\Http;

use Dflydev\FigCookies\Cookie;
use Dflydev\FigCookies\FigRequestCookies;
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

                $data->set('foo', $containerValue);

                return true;
            });

        // populate the cookies
        $firstResponse = $middleware(new ServerRequest(), new Response(), $containerPopulationMiddleware);

        $response = $middleware(
            FigRequestCookies::set(
                new ServerRequest(),
                Cookie::create(
                    SessionMiddleware::DEFAULT_COOKIE,
                    FigResponseCookies::get($firstResponse, SessionMiddleware::DEFAULT_COOKIE)->getValue()
                )
            ),
            new Response()
        );

        self::assertNotEmpty(FigResponseCookies::get($response, SessionMiddleware::DEFAULT_COOKIE)->getValue());
    }

    /**
     * @dataProvider validMiddlewaresProvider
     */
    public function testWillIgnoreRequestsWithExpiredTokens(SessionMiddleware $middleware)
    {
        // may the gods forgive me
        $expiredTokenRequest = FigRequestCookies::set(
            new ServerRequest(),
            Cookie::create(
                SessionMiddleware::DEFAULT_COOKIE,
                (string) (new Builder())
                    ->setIssuedAt((new \DateTime('-1 day'))->getTimestamp())
                    ->setExpiration((new \DateTime('-2 day'))->getTimestamp())
                    ->set(SessionMiddleware::SESSION_CLAIM, Data::fromTokenData(['foo' => 'bar'], []))
                    ->sign($this->getSigner($middleware), $this->getSignatureKey($middleware))
                    ->getToken()
            )
        );

        $checkingMiddleware = $this->buildFakeMiddleware(function (ServerRequestInterface $request) {
            /* @var $data Data */
            $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

            self::assertTrue($data->isEmpty());

            return true;
        });

        $middleware($expiredTokenRequest, new Response(), $checkingMiddleware);
    }

    public function testRequiresTokenExpirationValidation()
    {
        self::markTestIncomplete();
    }

    public function testAllowsModifyingHeaderDetails()
    {
        self::markTestIncomplete();
    }

    public function testRemovesSessionCookieOnEmptySessionContainer()
    {
        self::markTestIncomplete();
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
            $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

            $data->set('foo', 'bar');

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
                $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

                $data->set('foo', $containerValue);

                return true;
            });

        $containerCheckingMiddleware = $this
            ->buildFakeMiddleware(function (ServerRequestInterface $request) use ($containerValue) {
                /* @var $data Data */
                $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

                self::assertSame($containerValue, $data->get('foo'));

                return true;
            });

        $response = $middleware(new ServerRequest(), new Response(), $containerPopulationMiddleware);

        $middleware(
            FigRequestCookies::set(
                new ServerRequest(),
                Cookie::create(
                    SessionMiddleware::DEFAULT_COOKIE,
                    FigResponseCookies::get($response, SessionMiddleware::DEFAULT_COOKIE)->getValue()
                )
            ),
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
                $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

                $data->set('someproperty', 'someValue');

                return true;
            });

        $containerCheckingMiddleware = $this
            ->buildFakeMiddleware(function (ServerRequestInterface $request) {
                /* @var $data Data */
                $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

                self::assertFalse($data->has('someValue'));

                return true;
            });

        $response = $middleware(new ServerRequest(), new Response(), $containerPopulationMiddleware);

        $middleware(
            FigRequestCookies::set(
                new ServerRequest(),
                Cookie::create(
                    SessionMiddleware::DEFAULT_COOKIE,
                    FigResponseCookies::get($response, SessionMiddleware::DEFAULT_COOKIE)->getValue()
                )
            ),
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
            [SessionMiddleware::fromAsymmetricKey(
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
