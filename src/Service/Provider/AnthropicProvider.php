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
        private string $endpoint = 'https://api.anthropic.com/v1/messages'
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
                'content' => $this->buildUserPrompt($text),
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
- Return only valid JSON with keys: title, creators, date, publisher, publication, abstract, subjects, identifiers, language.
PROMPT;
    }

    private function buildUserPrompt(string $text): string
    {
        return "Source text:\n{$text}";
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
            CURLOPT_TIMEOUT => 60,
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
        return [
            'title' => $this->normalizeString($raw['title'] ?? ''),
            'creators' => $this->normalizeStringList($raw['creators'] ?? []),
            'date' => $this->normalizeString($raw['date'] ?? ''),
            'publisher' => $this->normalizeString($raw['publisher'] ?? ''),
            'publication' => $this->normalizeString($raw['publication'] ?? ''),
            'abstract' => $this->normalizeString($raw['abstract'] ?? ''),
            'subjects' => $this->normalizeStringList($raw['subjects'] ?? []),
            'identifiers' => $this->normalizeStringList($raw['identifiers'] ?? []),
            'language' => $this->normalizeString($raw['language'] ?? ''),
            'confidence' => [],
            'raw' => [
                'provider' => $this->name,
                'model' => (string) ($response['model'] ?? $this->model),
                'response_id' => (string) ($response['id'] ?? ''),
            ],
        ];
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
