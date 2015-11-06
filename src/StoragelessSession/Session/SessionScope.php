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

namespace StoragelessSession\Session;

final class SessionScope implements \JsonSerializable
{
    /**
     * @var \DateTimeImmutable|null
     */
    private $expirationTime;

    /**
     * @var bool
     */
    private $isModified = false;

    /**
     * @var mixed[]
     */
    private $data = [];

    /**
     * @param mixed[]                 $value
     * @param \DateTimeImmutable|null $expirationTime
     */
    private function __construct(array $value, \DateTimeImmutable $expirationTime = null)
    {
        $this->data           = $value;
        $this->expirationTime = $expirationTime;
        $this->resetIfPastExpiration();
    }

    /**
     * @param array                   $value
     * @param \DateTimeImmutable|null $expirationTime
     *
     * @return self
     */
    public static function fromArrayAndExpiration(array $value, \DateTimeImmutable $expirationTime = null) : self
    {
        return new self($value, $expirationTime);
    }

    public function set(string $key, $value)
    {
        $this->data[$key] = $value;
        $this->isModified = true;
    }

    public function get(string $key, $default = null)
    {
        $this->resetIfPastExpiration();

        return $this->data[$key] ?? $default;
    }

    public function remove(string $key)
    {
        unset($this->data[$key]);
        $this->isModified = true;
    }

    public function isModified() : bool
    {
        return $this->isModified;
    }

    public function isEmpty() : bool
    {
        return empty($this->data);
    }

    private function resetIfPastExpiration()
    {
        if (null === $this->expirationTime) {
            return;
        }

        if (microtime(true) > $this->expirationTime->format('U')) {
            $this->data           = [];
            $this->expirationTime = new \DateTimeImmutable();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        return $this->data;
    }
}
