<?php

namespace StoragelessSession\Http;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Token;
use StoragelessSession\Session\Data;

class TokenSerializer
{
    public function __construct()
    {
        // @TODO inject token generation logic here (should be used both when serializing and deserializing)
    }

    public function serialize(Data $session): Token
    {
        // @todo this is just a mockup: it's wrong and it should rely on `Data` having a string representation
        // @todo also, should inject correct keys here
        return (new Builder())
            ->setIssuer('https://example.com')
            ->set('data', json_encode($session))
            ->getToken();
    }

    public function deSerialize(Token $token): Data
    {
        return Data::fromJsonString($token->getClaim('data'));
    }
}
