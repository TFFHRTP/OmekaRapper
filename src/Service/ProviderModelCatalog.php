<?php declare(strict_types=1);

namespace OmekaRapper\Service;

use RuntimeException;

class ProviderModelCatalog
{
    /**
     * @return string[]
     */
    public function listModels(string $provider, string $baseUrl, string $apiKey = ''): array
    {
        return match ($provider) {
            'chatgpt', 'codex' => $this->listOpenAiModels($baseUrl, $apiKey),
            'claude' => $this->listAnthropicModels($baseUrl, $apiKey),
            'ollama' => $this->listOllamaModels($baseUrl),
            default => throw new RuntimeException(sprintf('Unsupported provider "%s".', $provider)),
        };
    }

    /**
     * @return string[]
     */
    private function listOpenAiModels(string $baseUrl, string $apiKey): array
    {
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is required to load models.');
        }

        $endpoint = $this->replacePath($baseUrl, '/v1/models');
        $response = $this->requestJson($endpoint, [
            'Authorization: Bearer ' . $apiKey,
        ]);

        $models = [];
        foreach (($response['data'] ?? []) as $item) {
            if (is_array($item) && !empty($item['id'])) {
                $models[] = (string) $item['id'];
            }
        }

        return $this->normalizeModelList($models);
    }

    /**
     * @return string[]
     */
    private function listAnthropicModels(string $baseUrl, string $apiKey): array
    {
        if ($apiKey === '') {
            throw new RuntimeException('Anthropic API key is required to load models.');
        }

        $models = [];
        $endpoint = $this->replacePath($baseUrl, '/v1/models');
        $headers = [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ];
        $nextAfterId = null;

        do {
            $pagedEndpoint = $nextAfterId
                ? $endpoint . '?after_id=' . rawurlencode($nextAfterId)
                : $endpoint;
            $response = $this->requestJson($pagedEndpoint, $headers);

            foreach (($response['data'] ?? []) as $item) {
                if (is_array($item) && !empty($item['id'])) {
                    $models[] = (string) $item['id'];
                }
            }

            $hasMore = !empty($response['has_more']);
            $nextAfterId = $hasMore && !empty($response['last_id'])
                ? (string) $response['last_id']
                : null;
        } while ($nextAfterId !== null);

        return $this->normalizeModelList($models);
    }

    /**
     * @return string[]
     */
    private function listOllamaModels(string $baseUrl): array
    {
        $models = [];
        foreach (['/api/tags', '/api/ps'] as $path) {
            $endpoint = $this->replacePath($baseUrl, $path);
            $response = $this->requestJson($endpoint, []);

            foreach (($response['models'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (!empty($item['name'])) {
                    $models[] = (string) $item['name'];
                }
                if (!empty($item['model'])) {
                    $models[] = (string) $item['model'];
                }
            }
        }

        return $this->normalizeModelList($models);
    }

    /**
     * @param string[] $headers
     */
    private function requestJson(string $url, array $headers): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL is required to load models.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize the model discovery request.');
        }

        $requestHeaders = array_merge(['Accept: application/json'], $headers);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $requestHeaders,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException($error !== '' ? $error : 'Model discovery request failed.');
        }

        if ($status < 200 || $status >= 300) {
            $decoded = json_decode($body, true);
            $message = is_array($decoded) && isset($decoded['error']['message'])
                ? (string) $decoded['error']['message']
                : sprintf('Model discovery request failed with HTTP %d.', $status);
            throw new RuntimeException($message);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Model discovery returned invalid JSON.');
        }

        return $decoded;
    }

    private function replacePath(string $baseUrl, string $path): string
    {
        $parts = parse_url($baseUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('Invalid provider endpoint URL.');
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        return sprintf('%s://%s%s%s', $parts['scheme'], $parts['host'], $port, $path);
    }

    /**
     * @param string[] $models
     * @return string[]
     */
    private function normalizeModelList(array $models): array
    {
        $models = array_values(array_unique(array_filter(array_map('trim', $models))));
        sort($models, SORT_NATURAL | SORT_FLAG_CASE);
        return $models;
    }
}
