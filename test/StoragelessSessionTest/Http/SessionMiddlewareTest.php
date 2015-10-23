<?php
namespace StoragelessSessionTest\Http;

use Dflydev\FigCookies\Cookie;
use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use Lcobucci\JWT\Parser;
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
    public function testInjectsSessionDataEvenWithNoNextMiddleware()
    {
        self::markTestIncomplete();
    }

    public function testRequiresValidToken()
    {
        self::markTestIncomplete();
    }

    public function testRequiresTokenSignatureValidation()
    {
        self::markTestIncomplete();
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

    public function testExtractsSessionContainerFromEmptyRequest()
    {
        $checkingMiddleware = $this->getMock(\stdClass::class, ['__invoke']);

        $checkingMiddleware
            ->expects(self::once())
            ->method('__invoke')
            ->with(self::callback(function (ServerRequestInterface $request) {
                self::assertInstanceOf(Data::class, $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE));

                return true;
            }))
            ->willReturn(new Response());

        self::assertInstanceOf(
            ResponseInterface::class,
            $this->defaultMiddleware()(new ServerRequest(), new Response(), $checkingMiddleware)
        );
    }

    public function testInjectsSessionInResponseCookies()
    {
        $checkingMiddleware = $this->getMock(\stdClass::class, ['__invoke']);

        $checkingMiddleware
            ->expects(self::once())
            ->method('__invoke')
            ->willReturnCallback(function (ServerRequestInterface $request) {
                /* @var $data Data */
                $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

                $data->set('foo', 'bar');

                return new Response();
            });

        $response = $this->defaultMiddleware()(new ServerRequest(), new Response(), $checkingMiddleware);

        self::assertEmpty(FigResponseCookies::get($response, 'non-existing')->getValue());
        self::assertInstanceOf(
            Token::class,
            (new Parser())->parse(FigResponseCookies::get($response, 'cookie-name')->getValue())
        );
    }

    public function testSessionContainerCanBeReusedOverMultipleRequests()
    {
        $containerValue                = uniqid('', true);
        $containerPopulationMiddleware = $this->getMock(\stdClass::class, ['__invoke']);
        $containerCheckingMiddleware   = $this->getMock(\stdClass::class, ['__invoke']);

        $containerPopulationMiddleware
            ->expects(self::once())
            ->method('__invoke')
            ->with(self::callback(function (ServerRequestInterface $request) use ($containerValue) {
                /* @var $data Data */
                $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

                $data->set('foo', $containerValue);

                return true;
            }))
            ->willReturn(new Response());

        $containerCheckingMiddleware
            ->expects(self::once())
            ->method('__invoke')
            ->with(self::callback(function (ServerRequestInterface $request) use ($containerValue) {
                /* @var $data Data */
                $data = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

                self::assertSame($containerValue, $data->get('foo'));

                return true;
            }))
            ->willReturn(new Response());

        $response = $this->defaultMiddleware()(new ServerRequest(), new Response(), $containerPopulationMiddleware);

        $this->defaultMiddleware()(
            FigRequestCookies::set(
                new ServerRequest(),
                Cookie::create('cookie-name', FigResponseCookies::get($response, 'cookie-name')->getValue())
            ),
            new Response(),
            $containerCheckingMiddleware
        );
    }

    /**
     * @return SessionMiddleware
     */
    private function defaultMiddleware()
    {
        return new SessionMiddleware(
            new Sha256(),
            'foo',
            'foo',
            SetCookie::create('cookie-name'),
            new Parser(),
            100
        );
    }
}
