<?php declare(strict_types=1);

namespace OmekaRapper\Service\Provider;

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
        $properties = [];
        $availableTerms = is_array($input['available_terms'] ?? null) ? $input['available_terms'] : [];
        $availableSet = array_fill_keys($availableTerms, true);

        $title = $this->guessTitle($text) ?? '(Untitled)';
        if (isset($availableSet['dcterms:title'])) {
            $properties[] = ['term' => 'dcterms:title', 'values' => [$title]];
        }
        if ($snippet !== '' && (isset($availableSet['dcterms:abstract']) || isset($availableSet['dcterms:description']))) {
            $properties[] = [
                'term' => isset($availableSet['dcterms:abstract']) ? 'dcterms:abstract' : 'dcterms:description',
                'values' => [$snippet],
            ];
        }

        // Always return the same schema our real providers will return.
        return [
            'title' => $title,
            'creators' => [],
            'date' => null,
            'publisher' => null,
            'publication' => null,
            'abstract' => $snippet !== '' ? $snippet : null,
            'subjects' => [],
            'identifiers' => [],
            'language' => null,
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
