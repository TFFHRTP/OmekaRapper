<?php declare(strict_types=1);

namespace OmekaRapper\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OmekaRapper\Service\PdfTextExtractor;

class PdfTextExtractorFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): PdfTextExtractor
    {
        $settings = $container->get('Omeka\Settings');

        return new PdfTextExtractor(
            trim((string) $settings->get('omekarapper_pdftotext_path', '')),
            trim((string) $settings->get('omekarapper_pdftoppm_path', '')),
            trim((string) $settings->get('omekarapper_tesseract_path', ''))
        );
    }
}
