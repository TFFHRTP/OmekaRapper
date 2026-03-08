<?php declare(strict_types=1);

namespace OmekaRapper\Service\Provider;

use OmekaRapper\Service\MetadataFieldMapper;

class DummyProvider implements ProviderInterface
{
    public function getName(): string
    {
        return 'dummy';
    }

    public function suggestCatalogMetadata(array $input, array $options = []): array
    {
        $text = (string) ($input['text'] ?? '');
        $snippet = mb_substr(trim($text), 0, 160);
        $availableTerms = is_array($input['available_terms'] ?? null) ? $input['available_terms'] : [];
        $availableProperties = is_array($input['available_properties'] ?? null) ? $input['available_properties'] : [];

        $title = $this->guessTitle($text) ?? '(Untitled)';
        $properties = MetadataFieldMapper::buildPropertySuggestions([
            'title' => $title,
            'abstract' => $snippet,
        ], $availableTerms, $availableProperties);

        // Always return the same schema our real providers will return.
        return [
            'title' => $title,
            'alternative_title' => null,
            'creators' => [],
            'contributors' => [],
            'date' => null,
            'publisher' => null,
            'publication' => null,
            'abstract' => $snippet !== '' ? $snippet : null,
            'subjects' => [],
            'identifiers' => [],
            'language' => null,
            'type' => null,
            'extent' => null,
            'rights' => null,
            'spatial' => null,
            'temporal' => null,
            'relation' => null,
            'source' => null,
            'format' => null,
            'properties' => $properties,
            'confidence' => [
                'title' => 0.10,
                'abstract' => 0.20,
            ],
            'raw' => [
                'note' => 'Dummy provider: wire up OpenAI/Claude next.',
            ],
        ];
    }

    private function guessTitle(string $text): ?string
    {
        $lines = preg_split('/\R/', trim($text)) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && mb_strlen($line) <= 120) {
                return $line;
            }
        }
        return null;
    }
}
