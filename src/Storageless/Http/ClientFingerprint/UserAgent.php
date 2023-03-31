<?php

declare(strict_types=1);

namespace PSR7Sessions\Storageless\Http\ClientFingerprint;

use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_string;

/** @immutable */
final class UserAgent implements Source
{
    public const REQUEST_ATTRIBUTE_NAME = 'HTTP_USER_AGENT';

    public function extractFrom(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        if (
            ! array_key_exists(self::REQUEST_ATTRIBUTE_NAME, $serverParams)
            || ! is_string($serverParams[self::REQUEST_ATTRIBUTE_NAME])
            || $serverParams[self::REQUEST_ATTRIBUTE_NAME] === ''
        ) {
            throw SourceMissing::for(self::REQUEST_ATTRIBUTE_NAME);
        }

        return $serverParams[self::REQUEST_ATTRIBUTE_NAME];
    }
}
