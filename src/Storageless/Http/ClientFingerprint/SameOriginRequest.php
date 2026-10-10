<?php

declare(strict_types=1);

namespace PSR7Sessions\Storageless\Http\ClientFingerprint;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\ConstraintViolation;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function base64_encode;
use function json_encode;
use function sodium_crypto_generichash;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * @internal
 *
 * @psalm-immutable
 */
final readonly class SameOriginRequest implements Constraint
{
    public const string CLAIM = 'fp';

    /** @var list<Source> */
    private array $sources;

    public function __construct(
        private Configuration $configuration,
        private ServerRequestInterface $serverRequest,
    ) {
        $this->sources = $this->configuration->sources();
    }

    public function assert(Token $token): void
    {
        if ($this->sources === []) {
            return;
        }

        if (! $token instanceof UnencryptedToken) {
            throw new RuntimeException(sprintf(
                'It was expected an %s, %s given',
                UnencryptedToken::class,
                $token::class,
            ));
        }

        if (! $token->claims()->has(self::CLAIM)) {
            throw ConstraintViolation::error('"Client Fingerprint" claim missing', $this);
        }

        try {
            $currentRequestFingerprint = self::getCurrentFingerprint($this->sources, $this->serverRequest);
        } catch (SourceMissing $sourceMissing) {
            throw ConstraintViolation::error(
                '"Client Fingerprint" cannot be computed: ' . $sourceMissing->getMessage(),
                $this,
            );
        }

        if ($token->claims()->get(self::CLAIM) !== $currentRequestFingerprint) {
            throw ConstraintViolation::error('"Client Fingerprint" does not match', $this);
        }
    }

    /** @throws SourceMissing */
    public function configure(Builder $builder): Builder
    {
        if ($this->sources === []) {
            return $builder;
        }

        return $builder->withClaim(self::CLAIM, self::getCurrentFingerprint($this->sources, $this->serverRequest));
    }

    /**
     * @param non-empty-list<Source> $sources
     *
     * @return non-empty-string
     *
     * @throws SourceMissing
     */
    private static function getCurrentFingerprint(array $sources, ServerRequestInterface $serverRequest): string
    {
        $fingerprintSource = [];
        foreach ($sources as $source) {
            $fingerprintSource[] = $source->extractFrom($serverRequest);
        }

        return base64_encode(sodium_crypto_generichash(json_encode($fingerprintSource, JSON_THROW_ON_ERROR)));
    }
}
