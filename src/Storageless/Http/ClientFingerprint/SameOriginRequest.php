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
use function base64_encode;
use function implode;
use function sodium_crypto_generichash;

/** @immutable */
final class SameOriginRequest implements Constraint
{
    public const CLAIM = 'fp';

    /** @var non-empty-string */
    private readonly string $currentRequestFingerprint;

    public function __construct(
        private readonly Configuration $configuration,
        ServerRequestInterface $serverRequest,
    ) {
        if (! $this->configuration->enabled()) {
            return;
        }

        $this->currentRequestFingerprint = self::getCurrentFingerprint($this->configuration, $serverRequest);
    }

    public function assert(Token $token): void
    {
        if (! $this->configuration->enabled()) {
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
        if (! $this->configuration->enabled()) {
            return $builder;
        }

        return $builder->withClaim(self::CLAIM, $this->currentRequestFingerprint);
    }

    /** @return non-empty-string */
    private static function getCurrentFingerprint(Configuration $configuration, ServerRequestInterface $serverRequest): string
    {
        $fingerprintSource = [];
        foreach ($configuration->sources() as $source) {
            $fingerprintSource[] = $source->extractFrom($serverRequest);
        }

        $fingerprint = base64_encode(sodium_crypto_generichash(implode("\x00", $fingerprintSource)));
        assert($fingerprint !== '');

        return $fingerprint;
    }
}
