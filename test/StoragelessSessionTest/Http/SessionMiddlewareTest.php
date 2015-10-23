<?php
namespace StoragelessSessionTest\Http;

use Dflydev\FigCookies\SetCookie;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
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

    /**
     * @return SessionMiddleware
     */
    private function defaultMiddleware()
    {
        return new SessionMiddleware(
            new Sha256(),
            'foo',
            'bar',
            SetCookie::create('cookie-name'),
            new Parser(),
            100
        );
    }
}
