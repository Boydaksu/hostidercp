<?php
declare(strict_types=1);
namespace Hostider\Core;

final class Container
{
    private array $factories = [];
    private array $instances = [];
    private array $resolving = [];

    public function bind(string $id, \Closure $factory): void
    {
        if (isset($this->factories[$id])) {
            throw new \LogicException('Duplicate binding');
        }
        $this->factories[$id] = $factory;
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (!isset($this->factories[$id]) || isset($this->resolving[$id])) {
            throw new \LogicException('Missing or circular dependency');
        }
        $this->resolving[$id] = true;
        try {
            return $this->instances[$id] = ($this->factories[$id])($this);
        } finally {
            unset($this->resolving[$id]);
        }
    }
}
