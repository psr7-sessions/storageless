<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

declare(strict_types=1);

namespace PSR7Sessions\Storageless\Session;

use JsonSerializable;
use stdClass;

use function array_key_exists;
use function count;
use function json_decode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

final class DefaultSessionData implements SessionInterface
{
    private const DEFAULT_JSON_DECODE_DEPTH = 512;

    /** @var array<string, int|bool|string|float|mixed[]|null> */
    private array $data;

    /** @var array<string, int|bool|string|float|mixed[]|null> */
    private array $originalData;

    /**
     * @param array<string, int|bool|string|float|mixed[]|null> $data
     * @param array<string, int|bool|string|float|mixed[]|null> $originalData
     */
    private function __construct(
        array $data,
        array $originalData
    ) {
        $this->data         = $data;
        $this->originalData = $originalData;
    }

    public static function fromDecodedTokenData(object $data): self
    {
        $arrayShapedData = self::convertValueToScalar($data);

        return new self($arrayShapedData, $arrayShapedData);
    }

    /**
     * @param array<int|bool|string|float|mixed[]|object|JsonSerializable|null> $data
     */
    public static function fromTokenData(array $data): self
    {
        $instance = new self([], []);

        foreach ($data as $key => $value) {
            $instance->set((string) $key, $value);
        }

        $instance->originalData = $instance->data;

        return $instance;
    }

    public static function newEmptySession(): self
    {
        return new self([], []);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, $value): void
    {
        $this->data[$key] = self::convertValueToScalar($value);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, $default = null)
    {
        if (! $this->has($key)) {
            return self::convertValueToScalar($default);
        }

        return $this->data[$key];
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function hasChanged(): bool
    {
        return $this->data !== $this->originalData;
    }

    public function isEmpty(): bool
    {
        return ! count($this->data);
    }

    public function jsonSerialize(): object
    {
        return (object) $this->data;
    }

    /**
     * @param int|bool|string|float|mixed[]|object|JsonSerializable|null $value
     * @psalm-param ValueTypeWithObjects $value
     *
     * @return int|bool|string|float|mixed[]|null
     * @psalm-return (ValueTypeWithObjects is object ? array<string, ValueType> : ValueType)
     *
     * @psalm-template ValueType of int|bool|string|float|array<mixed>|null
     * @psalm-template ValueTypeWithObjects of ValueType|object
     */
    private static function convertValueToScalar(int|bool|string|float|array|object|null $value): int|bool|string|float|array|null
    {
        /** @psalm-var ValueType $decoded */
        $decoded = json_decode(
            json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
            true,
            self::DEFAULT_JSON_DECODE_DEPTH,
            JSON_THROW_ON_ERROR
        );

        return $decoded;
    }
}
