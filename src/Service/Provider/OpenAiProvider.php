<?php declare(strict_types=1);

namespace OmekaRapper\Service\Provider;

use RuntimeException;

class OpenAiProvider implements ProviderInterface
{
    private const MODE_RESPONSES = 'responses';
    private const MODE_CHAT = 'chat';

    public function __construct(
        private string $name,
        private string $label,
        private string $model = 'gpt-4o-mini',
        private string $endpoint = 'https://api.openai.com/v1/responses',
        private string $apiKey = '',
        private string $mode = self::MODE_RESPONSES,
        private bool $requireApiKey = true,
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

        if ($this->requireApiKey && $this->apiKey === '') {
            throw new RuntimeException(sprintf('%s is enabled, but no API key is configured.', $this->label));
        }

        $payload = $this->mode === self::MODE_CHAT
            ? $this->buildChatPayload($text, $input, $options)
            : $this->buildResponsesPayload($text, $input, $options);

        $response = $this->postJson($payload);
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('%s returned invalid JSON.', $this->label));
        }

        $jsonText = $this->mode === self::MODE_CHAT
            ? $this->extractChatJson($decoded)
            : $this->extractResponsesJson($decoded);
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
- Use the properties array to return term-keyed values for the current item form.
- Return only valid JSON.
PROMPT;
    }

    private function buildUserPrompt(string $text): string
    {
        return "Source text:\n{$text}";
    }

    private function buildResponsesPayload(string $text, array $input, array $options): array
    {
        $userPrompt = $this->buildUserPrompt($text) . $this->buildAvailableTermsPrompt($input);
        return [
            'model' => $options['model'] ?? $this->model,
            'input' => [[
                'role' => 'system',
                'content' => [[
                    'type' => 'input_text',
                    'text' => $this->buildSystemPrompt(),
                ]],
            ], [
                'role' => 'user',
                'content' => [[
                    'type' => 'input_text',
                    'text' => $userPrompt,
                ]],
            ]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'catalog_metadata',
                    'schema' => $this->responseSchema(),
                    'strict' => true,
                ],
            ],
        ];
    }

    private function buildChatPayload(string $text, array $input, array $options): array
    {
        $userPrompt = $this->buildUserPrompt($text)
            . $this->buildAvailableTermsPrompt($input)
            . "\n\nReturn a single JSON object with keys: title, creators, date, publisher, publication, abstract, subjects, identifiers, language, properties.";
        return [
            'model' => $options['model'] ?? $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->buildSystemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
        ];
    }

    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'title',
                'creators',
                'date',
                'publisher',
                'publication',
                'abstract',
                'subjects',
                'identifiers',
                'language',
            ],
            'properties' => [
                'title' => ['type' => 'string'],
                'creators' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'date' => ['type' => 'string'],
                'publisher' => ['type' => 'string'],
                'publication' => ['type' => 'string'],
                'abstract' => ['type' => 'string'],
                'subjects' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'identifiers' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'language' => ['type' => 'string'],
                'properties' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['term', 'values'],
                        'properties' => [
                            'term' => ['type' => 'string'],
                            'values' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function postJson(array $payload): string
    {
        $ch = curl_init($this->endpoint);
        if ($ch === false) {
            throw new RuntimeException(sprintf('Could not initialize the %s request.', $this->label));
        }

        $headers = [
            'Content-Type: application/json',
        ];
        if ($this->apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max(1, $this->timeout),
            CURLOPT_HTTPHEADER => $headers,
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

    private function extractResponsesJson(array $response): string
    {
        if (!empty($response['output']) && is_array($response['output'])) {
            foreach ($response['output'] as $outputItem) {
                if (!is_array($outputItem) || empty($outputItem['content']) || !is_array($outputItem['content'])) {
                    continue;
                }
                foreach ($outputItem['content'] as $contentItem) {
                    if (!is_array($contentItem)) {
                        continue;
                    }
                    if (($contentItem['type'] ?? null) === 'output_text' && isset($contentItem['text'])) {
                        return (string) $contentItem['text'];
                    }
                }
            }
        }

        throw new RuntimeException(sprintf('%s returned no usable output text.', $this->label));
    }

    private function extractChatJson(array $response): string
    {
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (is_string($content) && trim($content) !== '') {
            return $content;
        }

        if (is_array($content)) {
            foreach ($content as $contentItem) {
                if (is_array($contentItem) && isset($contentItem['text']) && trim((string) $contentItem['text']) !== '') {
                    return (string) $contentItem['text'];
                }
            }
        }

        throw new RuntimeException(sprintf('%s returned no usable chat completion text.', $this->label));
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
                'mode' => $this->mode,
            ],
        ];
    }

    private function buildAvailableTermsPrompt(array $input): string
    {
        $terms = is_array($input['available_terms'] ?? null) ? $input['available_terms'] : [];
        $terms = array_values(array_filter(array_map(static fn($term) => trim((string) $term), $terms)));
        if ($terms === []) {
            return '';
        }

        return "\n\nAvailable Omeka property terms for this item form:\n- "
            . implode("\n- ", $terms)
            . "\n\nUse the properties array to return only terms from this list when you can infer values confidently. Prefer Dublin Core and base resource fields when applicable. Return as many of these terms as the source supports.";
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
            $normalized[] = [
                'term' => $term,
                'values' => $values,
            ];
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
