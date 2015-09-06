<?php

namespace StoragelessSession\Http;

use Psr\Http\Message\RequestInterface;

class HttpTokenStringValidator
{
    public function __invoke(string $token, RequestInterface $request): bool
    {
        // @TODO JWT validation required here
        // @TODO additional validators required here, eventually
        return true;
    }
}
