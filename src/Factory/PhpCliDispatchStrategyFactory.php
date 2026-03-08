<?php declare(strict_types=1);

namespace OmekaRapper\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Job\DispatchStrategy\PhpCli;

class PhpCliDispatchStrategyFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null): PhpCli
    {
        $viewHelpers = $services->get('ViewHelperManager');
        $basePathHelper = $viewHelpers->get('BasePath');
        $serverUrlHelper = $viewHelpers->get('ServerUrl');
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');

        $phpPath = trim((string) $settings->get('omekarapper_phpcli_path', ''));
        if ($phpPath === '' && isset($config['cli']['phpcli_path']) && $config['cli']['phpcli_path']) {
            $phpPath = (string) $config['cli']['phpcli_path'];
        }

        return new PhpCli(
            $services->get('Omeka\Cli'),
            $basePathHelper(),
            $serverUrlHelper(),
            $phpPath !== '' ? $phpPath : null
        );
    }
}
