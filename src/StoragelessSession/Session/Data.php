<?php

declare(strict_types=1);

namespace StoragelessSession\Session;

class Data
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

    public static function fromTokenData(array $data, array $metadata): self
    {
        return new self($data, $metadata);
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
    }

    public function remove(string $key)
    {
        unset($this->data[$key]);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    // @TODO ArrayAccess stuff? Or Containers? (probably better to just allow plain keys)
}
