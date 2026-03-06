<?php declare(strict_types=1);

namespace OmekaRapper\Service;

use OmekaRapper\Service\Provider\ProviderInterface;
use RuntimeException;

class AiClientManager
{
    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    /**
     * @param ProviderInterface[] $providers
     */
    public function __construct(array $providers)
    {
        foreach ($providers as $p) {
            $this->providers[$p->getName()] = $p;
        }
    }

    public function get(string $name): ProviderInterface
    {
        if (!isset($this->providers[$name])) {
            $known = implode(', ', array_keys($this->providers));
            throw new RuntimeException("Unknown provider '{$name}'. Known: {$known}");
        }
        return $this->providers[$name];
    }

    /**
     * Convenience for UI dropdowns later.
     * @return string[]
     */
    public function listProviderNames(): array
    {
        return array_keys($this->providers);
    }
}