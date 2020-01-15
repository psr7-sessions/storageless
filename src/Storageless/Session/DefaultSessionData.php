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

use InvalidArgumentException;
use JsonSerializable;
use stdClass;
use function array_key_exists;
use function assert;
use function count;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function sprintf;
use function var_export;
use const JSON_PRESERVE_ZERO_FRACTION;

final class DefaultSessionData implements SessionInterface
{
    /** @var array<string, int|bool|string|float|mixed[]|null> */
    private array $data;

    /** @var array<string, int|bool|string|float|mixed[]|null> */
    private array $originalData;

    /**
     * Instantiation via __construct is not allowed, use
     * - {@see DefaultSessionData::fromDecodedTokenData}
     * - {@see DefaultSessionData::fromTokenData}
     * - {@see DefaultSessionData::newEmptySession}
     * instead
     */
    private function __construct()
    {
    }

    public static function fromDecodedTokenData(stdClass $data) : self
    {
        $instance = new self();

        $arrayShapedData = self::convertValueToScalar($data);
        assert(is_array($arrayShapedData));

        $instance->originalData = $instance->data = $arrayShapedData;

        return $instance;
    }

    /** @param mixed[] $data */
    public static function fromTokenData(array $data) : self
    {
        $instance = new self();

        $instance->data = [];

        foreach ($data as $key => $value) {
            $instance->set((string) $key, $value);
        }

        $instance->originalData = $instance->data;

        return $instance;
    }

    public static function newEmptySession() : self
    {
        $instance = new self();

        $instance->originalData = $instance->data = [];

        return $instance;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, $value) : void
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

    public function remove(string $key) : void
    {
        unset($this->data[$key]);
    }

    public function clear() : void
    {
        $this->data = [];
    }

    public function has(string $key) : bool
    {
        return array_key_exists($key, $this->data);
    }

    public function hasChanged() : bool
    {
        return $this->data !== $this->originalData;
    }

    public function isEmpty() : bool
    {
        return ! count($this->data);
    }

    public function jsonSerialize() : object
    {
        return (object) $this->data;
    }

    /**
     * @param int|bool|string|float|mixed[]|object|JsonSerializable|null $value
     *
     * @return int|bool|string|float|mixed[]
     */
    private static function convertValueToScalar($value)
    {
        $jsonScalar = json_encode($value, JSON_PRESERVE_ZERO_FRACTION);

        if (! is_string($jsonScalar)) {
            // @TODO use PHP 7.3 and JSON_THROW_ON_ERROR instead? https://wiki.php.net/rfc/json_throw_on_error
            throw new InvalidArgumentException(sprintf(
                'Could not serialise given value %s due to %s (%s)',
                var_export($value, true),
                json_last_error_msg(),
                json_last_error()
            ));
        }

        return json_decode($jsonScalar, true);
    }
}
