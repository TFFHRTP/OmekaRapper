<?php declare(strict_types=1);

namespace OmekaRapper\Service\Provider;

use RuntimeException;

class AnthropicProvider implements ProviderInterface
{
    public function __construct(
        private string $name,
        private string $label,
        private string $apiKey,
        private string $model = 'claude-sonnet-4-0',
        private string $endpoint = 'https://api.anthropic.com/v1/messages',
        private int $timeout = 25
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function suggestCatalogMetadata(array $input, array $options = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(sprintf('The %s provider requires the PHP cURL extension.', $this->label));
        }

        $text = trim((string) ($input['text'] ?? ''));
        if ($text === '') {
            throw new RuntimeException('No source text provided.');
        }

        if ($this->apiKey === '') {
            throw new RuntimeException(sprintf('%s is enabled, but no API key is configured.', $this->label));
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'max_tokens' => 1200,
            'system' => $this->buildSystemPrompt(),
            'messages' => [[
                'role' => 'user',
                'content' => $this->buildUserPrompt($text, $input),
            ]],
        ];

        $response = $this->postJson($payload);
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('%s returned invalid JSON.', $this->label));
        }

        $jsonText = $this->extractMessageJson($decoded);
        $rawSuggestions = json_decode($jsonText, true);
        if (!is_array($rawSuggestions)) {
            throw new RuntimeException(sprintf('%s did not return valid metadata JSON.', $this->label));
        }

        return $this->normalizeSuggestions($rawSuggestions, $decoded);
    }

    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
Extract likely descriptive metadata for an Omeka S item.

Rules:
- Return conservative guesses only.
- If a field is unknown, use an empty string or empty array.
- Keep the abstract to 1-3 sentences.
- Use short controlled labels for subjects where possible.
- Return creators and identifiers as arrays of strings.
- Prefer filling as many available Omeka properties as the source supports.
- Return only valid JSON with keys: title, creators, date, publisher, publication, abstract, subjects, identifiers, language, properties.
- The properties key should be an array of objects: { "term": "...", "values": ["..."] }.
PROMPT;
    }

    private function buildUserPrompt(string $text, array $input): string
    {
        $prompt = "Source text:\n{$text}";
        $terms = is_array($input['available_terms'] ?? null) ? $input['available_terms'] : [];
        $terms = array_values(array_filter(array_map(static fn($term) => trim((string) $term), $terms)));
        if ($terms !== []) {
            $prompt .= "\n\nAvailable Omeka property terms for this item form:\n- "
                . implode("\n- ", $terms)
                . "\n\nUse the properties array to return only terms from this list when you can infer values confidently. Return as many of these terms as the source supports.";
        }
        return $prompt;
    }

    private function postJson(array $payload): string
    {
        $ch = curl_init($this->endpoint);
        if ($ch === false) {
            throw new RuntimeException(sprintf('Could not initialize the %s request.', $this->label));
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max(1, $this->timeout),
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException($error !== '' ? $error : sprintf('The %s request failed.', $this->label));
        }

        if ($status < 200 || $status >= 300) {
            $decoded = json_decode($body, true);
            $message = is_array($decoded) && isset($decoded['error']['message'])
                ? (string) $decoded['error']['message']
                : sprintf('%s request failed with HTTP %d.', $this->label, $status);
            throw new RuntimeException($message);
        }

        return $body;
    }

    private function extractMessageJson(array $response): string
    {
        if (!empty($response['content']) && is_array($response['content'])) {
            foreach ($response['content'] as $contentItem) {
                if (($contentItem['type'] ?? null) === 'text' && !empty($contentItem['text'])) {
                    return (string) $contentItem['text'];
                }
            }
        }

        throw new RuntimeException(sprintf('%s returned no usable message text.', $this->label));
    }

    private function normalizeSuggestions(array $raw, array $response): array
    {
        $properties = $this->normalizePropertySuggestions($raw['properties'] ?? []);
        return [
            'title' => $this->normalizeString($raw['title'] ?? '') ?? $this->firstPropertyValue($properties, ['dcterms:title']),
            'creators' => $this->normalizeStringList($raw['creators'] ?? []),
            'date' => $this->normalizeString($raw['date'] ?? '') ?? $this->firstPropertyValue($properties, ['dcterms:date']),
            'publisher' => $this->normalizeString($raw['publisher'] ?? '') ?? $this->firstPropertyValue($properties, ['dcterms:publisher']),
            'publication' => $this->normalizeString($raw['publication'] ?? '') ?? $this->firstPropertyValue($properties, ['dcterms:isPartOf', 'bibo:Journal']),
            'abstract' => $this->normalizeString($raw['abstract'] ?? '') ?? $this->firstPropertyValue($properties, ['dcterms:abstract', 'dcterms:description']),
            'subjects' => $this->normalizeStringList($raw['subjects'] ?? []),
            'identifiers' => $this->normalizeStringList($raw['identifiers'] ?? []),
            'language' => $this->normalizeString($raw['language'] ?? '') ?? $this->firstPropertyValue($properties, ['dcterms:language']),
            'properties' => $this->mergeDerivedProperties($properties, $raw),
            'confidence' => [],
            'raw' => [
                'provider' => $this->name,
                'model' => (string) ($response['model'] ?? $this->model),
                'response_id' => (string) ($response['id'] ?? ''),
            ],
        ];
    }

    private function normalizePropertySuggestions(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $properties = [];
        foreach ($value as $property) {
            if (!is_array($property)) {
                continue;
            }

            $term = trim((string) ($property['term'] ?? ''));
            $values = $this->normalizeStringList($property['values'] ?? []);
            if ($term === '' || $values === []) {
                continue;
            }

            $properties[$term] = array_values(array_unique(array_merge($properties[$term] ?? [], $values)));
        }

        $normalized = [];
        foreach ($properties as $term => $values) {
            $normalized[] = ['term' => $term, 'values' => $values];
        }
        return $normalized;
    }

    private function firstPropertyValue(array $properties, array $terms): ?string
    {
        foreach ($terms as $term) {
            foreach ($properties as $property) {
                if (($property['term'] ?? '') === $term && !empty($property['values'][0])) {
                    return (string) $property['values'][0];
                }
            }
        }
        return null;
    }

    private function mergeDerivedProperties(array $properties, array $raw): array
    {
        $map = [];
        foreach ($properties as $property) {
            $map[$property['term']] = $property['values'];
        }

        $derived = [
            'dcterms:title' => $this->normalizeStringList([$raw['title'] ?? '']),
            'dcterms:creator' => $this->normalizeStringList($raw['creators'] ?? []),
            'dcterms:date' => $this->normalizeStringList([$raw['date'] ?? '']),
            'dcterms:publisher' => $this->normalizeStringList([$raw['publisher'] ?? '']),
            'dcterms:abstract' => $this->normalizeStringList([$raw['abstract'] ?? '']),
            'dcterms:subject' => $this->normalizeStringList($raw['subjects'] ?? []),
            'dcterms:identifier' => $this->normalizeStringList($raw['identifiers'] ?? []),
            'dcterms:language' => $this->normalizeStringList([$raw['language'] ?? '']),
            'dcterms:isPartOf' => $this->normalizeStringList([$raw['publication'] ?? '']),
        ];

        foreach ($derived as $term => $values) {
            if ($values === []) {
                continue;
            }
            $map[$term] = array_values(array_unique(array_merge($map[$term] ?? [], $values)));
        }

        $normalized = [];
        foreach ($map as $term => $values) {
            $normalized[] = ['term' => $term, 'values' => $values];
        }
        return $normalized;
    }

    private function normalizeString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $entry) {
            $entry = trim((string) $entry);
            if ($entry !== '') {
                $normalized[] = $entry;
            }
        }

        return array_values(array_unique($normalized));
    }
}
