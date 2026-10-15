<?php

declare(strict_types=1);

namespace LearnUponLMS\Core;

/**
 * Minimal service registry. Lets Plugin::init() build components lazily
 * instead of instantiating everything inline.
 */
final class Container
{
    /** @var array<string, callable> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }

    public function get(string $id): object
    {
        if (!isset($this->instances[$id])) {
            if (!isset($this->factories[$id])) {
                throw new \OutOfBoundsException("Service not registered: {$id}");
            }

            $this->instances[$id] = ($this->factories[$id])($this);
        }

        return $this->instances[$id];
    }
}
