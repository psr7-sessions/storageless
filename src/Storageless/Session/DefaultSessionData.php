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

final class DefaultSessionData implements SessionInterface
{
    /**
     * @var array
     */
    private $data;

    /**
     * @var array
     */
    private $originalData;

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

    public static function fromDecodedTokenData(\stdClass $data) : self
    {
        $instance = new self();

        /** @var array $arrayShapedData */
        $arrayShapedData = self::convertValueToScalar($data);

        $instance->originalData = $instance->data = $arrayShapedData;

        return $instance;
    }

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

    /**
     * {@inheritDoc}
     */
    public function remove(string $key) : void
    {
        unset($this->data[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function clear() : void
    {
        $this->data = [];
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key) : bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * {@inheritDoc}
     */
    public function hasChanged() : bool
    {
        return $this->data !== $this->originalData;
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty() : bool
    {
        return empty($this->data);
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize() : object
    {
        return (object) $this->data;
    }

    /**
     * @param int|bool|string|float|array|object|\JsonSerializable|null $value
     *
     * @return int|bool|string|float|array
     */
    private static function convertValueToScalar($value)
    {
        $jsonScalar = json_encode($value, \JSON_PRESERVE_ZERO_FRACTION);

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
