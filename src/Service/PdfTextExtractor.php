<?php declare(strict_types=1);

namespace OmekaRapper\Service;

use RuntimeException;

class PdfTextExtractor
{
    private const OCR_PAGE_LIMIT = 10;
    private const COMMAND_PATHS = [
        '/opt/homebrew/bin',
        '/usr/local/bin',
        '/usr/bin',
        '/bin',
    ];

    public function __construct(
        private string $pdftotextPath = '',
        private string $pdftoppmPath = '',
        private string $tesseractPath = ''
    ) {}

    public function extract(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException('Uploaded PDF could not be read.');
        }

        if (!$this->looksLikePdf($filePath)) {
            throw new RuntimeException('Uploaded file is not a valid PDF.');
        }

        $pdftotext = $this->resolveCommand('pdftotext', $this->pdftotextPath);
        if ($pdftotext === null) {
            throw new RuntimeException('PDF import requires pdftotext. Install poppler to enable PDF extraction.');
        }

        $text = $this->runPdftotext($filePath, $pdftotext);
        if ($text !== '') {
            return [
                'text' => $text,
                'ocr_used' => false,
            ];
        }

        $pdftoppm = $this->resolveCommand('pdftoppm', $this->pdftoppmPath);
        $tesseract = $this->resolveCommand('tesseract', $this->tesseractPath);
        if ($pdftoppm === null || $tesseract === null) {
            throw new RuntimeException(
                'No extractable text was found in the PDF, and OCR fallback is not available.'
            );
        }

        $ocrText = $this->runOcr($filePath, $pdftoppm, $tesseract);
        if ($ocrText === '') {
            throw new RuntimeException('OCR could not extract text from the PDF.');
        }

        return [
            'text' => $ocrText,
            'ocr_used' => true,
        ];
    }

    private function looksLikePdf(string $filePath): bool
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 5);
        fclose($handle);

        return $header === '%PDF-';
    }

    private function runPdftotext(string $filePath, string $pdftotext): string
    {
        $command = sprintf(
            '%s -enc UTF-8 -layout -nopgbrk %s -',
            escapeshellarg($pdftotext),
            escapeshellarg($filePath)
        );

        return $this->runCommand($command, 'Could not start PDF text extraction.');
    }

    private function runOcr(string $filePath, string $pdftoppm, string $tesseract): string
    {
        $tempDir = sys_get_temp_dir() . '/omekarapper-ocr-' . bin2hex(random_bytes(8));
        if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
            throw new RuntimeException('Could not create a temporary OCR directory.');
        }

        try {
            $imagePrefix = $tempDir . '/page';
            $ppmCommand = sprintf(
                '%s -png -f 1 -l %d -r 200 %s %s',
                escapeshellarg($pdftoppm),
                self::OCR_PAGE_LIMIT,
                escapeshellarg($filePath),
                escapeshellarg($imagePrefix)
            );
            $this->runCommand($ppmCommand, 'Could not start PDF OCR conversion.');

            $images = glob($imagePrefix . '-*.png') ?: [];
            sort($images, SORT_NATURAL);
            if ($images === []) {
                return '';
            }

            $pages = [];
            foreach ($images as $imagePath) {
                $ocrCommand = sprintf(
                    '%s %s stdout --dpi 200 -l eng 2>/dev/null',
                    escapeshellarg($tesseract),
                    escapeshellarg($imagePath)
                );
                $pageText = trim($this->runCommand($ocrCommand, 'Could not start OCR processing.', false));
                if ($pageText !== '') {
                    $pages[] = $pageText;
                }
            }

            return trim(implode("\n\n", $pages));
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    private function runCommand(string $command, string $startupError, bool $throwOnFailure = true): string
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException($startupError);
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0 && $throwOnFailure) {
            $message = trim((string) $stderr);
            throw new RuntimeException($message !== '' ? $message : 'Command execution failed.');
        }

        return trim((string) $stdout);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($directory);
    }

    private function resolveCommand(string $command, string $configuredPath = ''): ?string
    {
        if ($configuredPath !== '') {
            $normalized = trim($configuredPath);
            if (is_file($normalized) && is_executable($normalized)) {
                return $normalized;
            }
            return null;
        }

        foreach (self::COMMAND_PATHS as $directory) {
            $path = $directory . '/' . $command;
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        $result = shell_exec(sprintf('command -v %s 2>/dev/null', escapeshellarg($command)));
        $resolved = trim((string) $result);
        return $resolved !== '' ? $resolved : null;
    }
}
