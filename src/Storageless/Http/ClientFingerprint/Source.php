<?php

declare(strict_types=1);

namespace PSR7Sessions\Storageless\Http\ClientFingerprint;

use Psr\Http\Message\ServerRequestInterface;

interface Source
{
    /** @return non-empty-string */
    public function extractFrom(ServerRequestInterface $request): string;
}
