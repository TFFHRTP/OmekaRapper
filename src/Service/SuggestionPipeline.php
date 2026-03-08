<?php declare(strict_types=1);

namespace OmekaRapper\Service;

use OmekaRapper\Service\Provider\ProviderInterface;
use RuntimeException;

class SuggestionPipeline
{
    private const PROVIDER_TEXT_LIMIT = 12000;

    public function __construct(
        private AiClientManager $clients,
        private SuggestionEnricher $suggestionEnricher
    ) {}

    public function generate(string $provider, string $sourceText, array $availableTerms = [], array $availableProperties = []): array
    {
        $providerWarning = null;
        $providerText = $this->prepareProviderText($sourceText);

        try {
            $client = $this->clients->get($provider);
            $suggestions = $client->suggestCatalogMetadata([
                'text' => $providerText,
                'available_terms' => $availableTerms,
                'available_properties' => $availableProperties,
            ]);
        } catch (RuntimeException $e) {
            $providerWarning = $e->getMessage();
            $suggestions = [];
        }

        $suggestions = $this->suggestionEnricher->enrich($suggestions, $sourceText, $availableTerms, $availableProperties);
        $raw = is_array($suggestions['raw'] ?? null) ? $suggestions['raw'] : [];

        if ($providerWarning !== null) {
            $raw['warning'] = $providerWarning;
            $raw['fallback_used'] = true;
        }

        if ($providerText !== $sourceText) {
            $raw['provider_input_truncated'] = true;
            $raw['provider_input_length'] = mb_strlen($providerText);
            $raw['source_text_length'] = mb_strlen($sourceText);
        }

        $suggestions['raw'] = $raw;

        return [
            'provider' => $provider,
            'suggestions' => $suggestions,
            'warning' => $providerWarning,
            'provider_text_length' => mb_strlen($providerText),
            'provider_text_truncated' => $providerText !== $sourceText,
        ];
    }

    public function shouldQueue(string $sourceText, bool $hasPdf): bool
    {
        return $hasPdf || mb_strlen($sourceText) > self::PROVIDER_TEXT_LIMIT;
    }

    private function prepareProviderText(string $text): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= self::PROVIDER_TEXT_LIMIT) {
            return $text;
        }

        $lines = preg_split('/\R+/', $text) ?: [];
        $priorityLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(title|author|authors|by|abstract|summary|keywords|subjects?|publisher|publication|journal|source|doi|isbn|issn|date)\b/i', $line)) {
                $priorityLines[] = $line;
            }
        }

        $priorityBlock = implode("\n", array_slice(array_values(array_unique($priorityLines)), 0, 20));
        $head = mb_substr($text, 0, 7000);
        $tail = mb_substr($text, -2500);

        $prepared = trim(implode("\n\n", array_filter([
            $priorityBlock !== '' ? "Priority metadata lines:\n" . $priorityBlock : '',
            "Beginning of source:\n" . trim($head),
            "End of source:\n" . trim($tail),
        ])));

        if (mb_strlen($prepared) > self::PROVIDER_TEXT_LIMIT) {
            $prepared = mb_substr($prepared, 0, self::PROVIDER_TEXT_LIMIT);
        }

        return $prepared;
    }
}
