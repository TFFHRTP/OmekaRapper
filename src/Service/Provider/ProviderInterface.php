<?php declare(strict_types=1);

namespace OmekaRapper\Service\Provider;

interface ProviderInterface
{
    public function getName(): string;

    /**
     * @param array $input e.g. ['text' => '...']
     * @param array $options e.g. model params later
     * @return array Normalized suggestions
     */
    public function suggestCatalogMetadata(array $input, array $options = []): array;
}