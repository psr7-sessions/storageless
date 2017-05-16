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

        $instance->originalData = $instance->data = self::convertValueToScalar($data);

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
    public function jsonSerialize()
    {
        return $this->data;
    }

    /**
     * @param int|bool|string|float|array|object|\JsonSerializable $value
     *
     * @return int|bool|string|float|array
     */
    private static function convertValueToScalar($value)
    {
        return json_decode(json_encode($value, \JSON_PRESERVE_ZERO_FRACTION), true);
    }
}
