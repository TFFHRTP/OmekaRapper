<?php declare(strict_types=1);

namespace OmekaRapper\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OmekaRapper\Service\AiClientManager;
use OmekaRapper\Service\SuggestionEnricher;
use OmekaRapper\Service\SuggestionPipeline;

class SuggestionPipelineFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): SuggestionPipeline
    {
        return new SuggestionPipeline(
            $container->get(AiClientManager::class),
            $container->get(SuggestionEnricher::class)
        );
    }
}
