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

final class Data implements \JsonSerializable
{
    const SCOPE_EXPIRATION = 'EXPIRE_KEYS';

    /**
     * @var array
     */
    private $data;

    /**
     * @var SessionScope[]
     */
    private $scopes = [];

    /**
     * @var array
     */
    private $metadata;

    /**
     * @todo ensure serializable data?
     */
    private function __construct(array $data, array $metadata)
    {
        $this->data     = $data;
        $this->metadata = $metadata;
        $this->expireDataFromScopes();
    }

    /**
     * @param string $scopeName
     *
     * @return SessionScope
     */
    public function getScope(string $scopeName)
    {
        if (isset($this->scopes[$scopeName])) {
            return $this->scopes[$scopeName];
        }

        $scopeData = $this->data[$scopeName] ?? [];

        return $this->scopes[$scopeName] = SessionScope::fromArrayAndExpiration(
            is_array($scopeData) ? $scopeData : [$scopeName => $scopeData],
            $this->getScopeExpiration($scopeName)
        );
    }

    /**
     * @param string $scopeName
     *
     * @return \DateTime|null
     */
    private function getScopeExpiration(string $scopeName)
    {
        if (! (
            isset($this->metadata[$scopeName][self::SCOPE_EXPIRATION])
            && is_int($this->metadata[$scopeName][self::SCOPE_EXPIRATION])
        )
        ) {
            return null;
        }

        return new \DateTimeImmutable('@' . $this->metadata[$scopeName][self::SCOPE_EXPIRATION]);
    }

    private function expireDataFromScopes()
    {
        $requestTime = microtime(true);

        foreach ($this->scopes as $key => $scope) {
            if ($requestTime > $scope->getExpirationTime()) {
                $scope->remove($key);
            }
        }
    }

    public static function fromDecodedTokenData(\stdClass $data)
    {
        return self::fromTokenData(self::convertStdClassToUsableStuff($data), []);
    }

    private static function convertStdClassToUsableStuff(\stdClass $shit)
    {
        $arrayData = [];

        foreach ($shit as $key => $property) {
            if ($property instanceof \stdClass) {
                $arrayData[$key] = self::convertStdClassToUsableStuff($property);

                continue;
            }

            $arrayData[$key] = $property;
        }

        return $arrayData;
    }

    public static function fromTokenData(array $data, array $metadata): self
    {
        return new self($data, $metadata);
    }

    public static function fromJsonString(string $jsonString)
    {
        $decoded = json_decode($jsonString);

        // @todo stronger validation here
        return new self($decoded['data'], $decoded['metadata']);
    }

    public static function newEmptySession(): self
    {
        return new self([], []);
    }

    public function isEmpty() : bool
    {
        foreach ($this->scopes as $scope) {
            if (! $scope->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    public function isModified() : bool
    {
        foreach ($this->scopes as $scope) {
            if ($scope->isModified()) {
                return true;
            }
        }

        return false;
    }

    // @TODO ArrayAccess stuff? Or Containers? (probably better to just allow plain keys)
    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        return array_filter($this->scopes);
    }
}
