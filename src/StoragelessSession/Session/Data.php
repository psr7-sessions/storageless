<?php

declare(strict_types=1);

namespace StoragelessSession\Session;

class Data implements \JsonSerializable
{
    /**
     * @var array
     */
    private $data;

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

    /**
     * @todo ensure serializable data?
     */
    public function set(string $key, $value)
    {
        $this->data[$key] = $value;
    }

    public function get(string $key)
    {
        if (! $this->has($key)) {
            throw new \OutOfBoundsException(sprintf('Non-existing key "%s" requested', $key));
        }

        return $this->data[$key];
    }

    public function remove(string $key)
    {
        unset($this->data[$key]);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function isEmpty()
    {
        return empty($this->data);
    }

    // @TODO ArrayAccess stuff? Or Containers? (probably better to just allow plain keys)
    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        return $this->data;
    }
}
