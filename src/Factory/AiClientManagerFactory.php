<?php declare(strict_types=1);

namespace OmekaRapper\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OmekaRapper\Service\AiClientManager;
use OmekaRapper\Service\Provider\DummyProvider;

class AiClientManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): AiClientManager
    {
        // Later: load config for enabled providers + credentials.
        $providers = [
            new DummyProvider(),
        ];

        return new AiClientManager($providers);
    }
}