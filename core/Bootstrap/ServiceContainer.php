<?php
namespace App\Bootstrap;

class ServiceContainer
{
    /** @var array<string,mixed> */
    private array $services = [];

    /**
     * @param string $name
     * @param mixed $service
     */
    public function register(string $name, $service): void
    {
        $this->services[$name] = $service;
    }

    /**
     * @template T
     * @param string $name
     * @return mixed
     */
    public function get(string $name)
    {
        if (!array_key_exists($name, $this->services)) {
            throw new \RuntimeException("Service not found: {$name}");
        }
        return $this->services[$name];
    }
}

