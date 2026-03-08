<?php declare(strict_types=1);

namespace OmekaRapper\Service;

class SuggestionResultStore
{
    public function save(int $jobId, array $payload): void
    {
        $path = $this->getPath($jobId);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function load(int $jobId): ?array
    {
        $path = $this->getPath($jobId);
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    private function getPath(int $jobId): string
    {
        return sys_get_temp_dir() . '/omekarapper-jobs/' . $jobId . '.json';
    }
}
