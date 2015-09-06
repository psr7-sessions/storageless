<?php

namespace StoragelessSession\Http;

use Psr\Http\Message\RequestInterface;
use StoragelessSession\Session\Data;

class SessionValidator
{
    public function __invoke(Data $session, RequestInterface $request): bool
    {
        // @TODO JWT validation required here
        // @TODO additional validators required here, eventually
        // note: additional validators can inject data into the session, for example:
        //  - origin address
        //  - origin user-agent
        return true;
    }
}
