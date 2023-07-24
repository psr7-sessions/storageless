<?php

declare(strict_types=1);

namespace PSR7Sessions\Storageless\Http\ClientFingerprint;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\ConstraintViolation;
use Psr\Http\Message\ServerRequestInterface;

use function assert;
use function implode;
use function sodium_bin2base64;
use function sodium_crypto_generichash;

use const SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING;

/** @immutable */
final class SameOriginRequest implements Constraint
{
    public const CLAIM = 'fp';

    /** @var list<Source> */
    private readonly array $sources;
    /** @var non-empty-string */
    private readonly string $currentRequestFingerprint;

    public function __construct(
        private readonly Configuration $configuration,
        ServerRequestInterface $serverRequest,
    ) {
        $this->sources = $this->configuration->sources();
        if ($this->sources === []) {
            return;
        }

        $this->currentRequestFingerprint = self::getCurrentFingerprint($this->sources, $serverRequest);
    }

    public function assert(Token $token): void
    {
        if ($this->sources === []) {
            return;
        }

        if (! $token instanceof UnencryptedToken) {
            throw ConstraintViolation::error('You should pass a plain token', $this);
        }

        if (! $token->claims()->has(self::CLAIM)) {
            throw ConstraintViolation::error('"Client Fingerprint" claim missing', $this);
        }

        if ($token->claims()->get(self::CLAIM) !== $this->currentRequestFingerprint) {
            throw ConstraintViolation::error('"Client Fingerprint" does not match', $this);
        }
    }

    public function configure(Builder $builder): Builder
    {
        if ($this->sources === []) {
            return $builder;
        }

        return $builder->withClaim(self::CLAIM, $this->currentRequestFingerprint);
    }

    /**
     * @param non-empty-list<Source> $sources
     *
     * @return non-empty-string
     */
    private static function getCurrentFingerprint(array $sources, ServerRequestInterface $serverRequest): string
    {
        $fingerprintSource = [];
        foreach ($sources as $source) {
            $fingerprintSource[] = $source->extractFrom($serverRequest);
        }

        $fingerprint = sodium_bin2base64(
            sodium_crypto_generichash(implode("\x00", $fingerprintSource)),
            SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING,
        );
        assert($fingerprint !== '');

        return $fingerprint;
    }
}
