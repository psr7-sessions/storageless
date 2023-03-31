<?php

declare(strict_types=1);

namespace PSR7Sessions\Storageless\Http\ClientFingerprint;

use RuntimeException;

/** @immutable */
final class Configuration
{
    /** @var list<Source> */
    private readonly array $sources;

    /** @no-named-arguments */
    public function __construct(
        Source ...$sources,
    ) {
        $this->sources = $sources;
    }

    public static function forIpAndUserAgent(): self
    {
        return new self(new RemoteAddr(), new UserAgent());
    }

    public function enabled(): bool
    {
        return $this->sources !== [];
    }

    /** @return non-empty-list<Source> */
    public function sources(): array
    {
        if ($this->sources === []) {
            throw new RuntimeException('No Source has been configured');
        }

        return $this->sources;
    }
}
