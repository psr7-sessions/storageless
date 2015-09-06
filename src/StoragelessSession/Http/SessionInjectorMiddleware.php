<?php

namespace StoragelessSession\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RequestSessionInjectorMiddleware
{
    /**
     * @var RequestSessionFactory
     */
    private $sessionFactory;

    public function __construct(RequestSessionFactory $sessionFactory, TokenSerializer $tokenSerializer)
    {
        $this->sessionFactory  = $sessionFactory;
        $this->tokenSerializer = $tokenSerializer;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $session = $this->sessionFactory->__invoke($request);

        /* @var $response ResponseInterface */
        $response = $next($request->withAttribute('Cookie', $session), $response);

        // note: session is mutable here
        return $response->withHeader('Cookie', $this->tokenSerializer->serialize($session));
    }
}