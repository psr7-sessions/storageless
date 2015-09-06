<?php

namespace StoragelessSession\Http;

use Lcobucci\JWT\Token;
use Psr\Http\Message\RequestInterface;

class TokenValidator
{
    public function __invoke(Token $token, RequestInterface $request): bool
    {
        // @TODO JWT validation required here
        // @TODO additional validators required here, eventually
        return true;
    }
}
