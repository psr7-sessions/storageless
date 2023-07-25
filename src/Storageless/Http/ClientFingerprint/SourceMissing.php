<?php

declare(strict_types=1);

namespace PSR7Sessions\Storageless\Http\ClientFingerprint;

use RuntimeException;

use function sprintf;

final class SourceMissing extends RuntimeException
{
    /** @param non-empty-string $parameter */
    public static function for(string $parameter): self
    {
        return new self(sprintf(
            'The request lacks a valid %s parameter',
            $parameter,
        ));
    }
}
