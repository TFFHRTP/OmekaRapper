<?php declare(strict_types=1);

namespace OmekaRapper\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OmekaRapper\Controller\AssistController;
use OmekaRapper\Service\AiClientManager;

class AssistControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): AssistController
    {
        return new AssistController($container->get(AiClientManager::class));
    }
}