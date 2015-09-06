<?php

namespace StoragelessSession\Http;

use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Token;
use Psr\Http\Message\RequestInterface;
use StoragelessSession\Session\Data;

class RequestSessionFactory
{
    /**
     * @var TokenValidator
     */
    private $tokenValidator;

    /**
     * @var SessionValidator
     */
    private $sessionValidator;

    public function __construct(
        TokenValidator $tokenValidator,
        SessionValidator $sessionValidator
    ) {
        $this->tokenValidator   = $tokenValidator;
        $this->sessionValidator = $sessionValidator;
    }

    public function getSessionForRequest(RequestInterface $request): Data
    {
        $tokens = array_filter(
            array_map(
                [$this, 'parseToken'],
                $request->getHeader('Cookie')
            ),
            function (Token $token) use ($request) {
                return $this->tokenValidator->__invoke($token, $request);
            }
        );

        /* @var $token Token|bool */
        $token = reset($tokens);

        $session = $token
            ? Data::fromTokenData($token->getClaims(), $token->getHeaders())
            : Data::newEmptySession();

        if (! $this->sessionValidator->__invoke($session, $request)) {
            // if all validation fails, simply reset the session (scrap it)
            return Data::newEmptySession();
        }

        return $session;
    }

    private function parseToken(string $token)
    {
        return (new Parser())->parse($token);
    }
}
