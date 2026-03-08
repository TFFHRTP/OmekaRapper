<?php declare(strict_types=1);

namespace OmekaRapper\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use OmekaRapper\Service\AiClientManager;
use OmekaRapper\Service\PdfTextExtractor;
use OmekaRapper\Service\ProviderModelCatalog;
use OmekaRapper\Service\SuggestionEnricher;
use OmekaRapper\Service\SuggestionPipeline;
use OmekaRapper\Service\SuggestionResultStore;
use RuntimeException;
use Throwable;

class AssistController extends AbstractActionController
{
    private const MAX_TEXT_LENGTH = 200000;
    private const MAX_PDF_BYTES = 26214400;

    public function __construct(
        private AiClientManager $clients,
        private ProviderModelCatalog $modelCatalog,
        private PdfTextExtractor $pdfTextExtractor,
        private SuggestionEnricher $suggestionEnricher,
        private SuggestionPipeline $suggestionPipeline,
        private SuggestionResultStore $suggestionResultStore
    ) {}

    public function indexAction(): JsonModel
    {
        $model = new JsonModel([
            'ok' => true,
            'module' => 'OmekaRapper',
            'message' => 'Use /admin/omeka-rapper/providers and POST /admin/omeka-rapper/suggest',
        ]);
        $model->setTerminal(true);
        return $model;
    }

    public function providersAction(): JsonModel
    {
        if (!$this->userIsAllowed('Omeka\Entity\Item', 'create')
            && !$this->userIsAllowed('Omeka\Entity\Item', 'update')
        ) {
            $this->getResponse()->setStatusCode(403);
            $model = new JsonModel([
                'ok' => false,
                'error' => 'You are not allowed to use the AI assistant.',
            ]);
            $model->setTerminal(true);
            return $model;
        }

        $model = new JsonModel([
            'ok' => true,
            'providers' => $this->clients->listProviderNames(),
            'default_provider' => $this->settings()->get('omekarapper_provider_default', 'dummy'),
        ]);
        $model->setTerminal(true);
        return $model;
    }

    public function suggestAction(): JsonModel
    {
        if (!$this->getRequest()->isPost()) {
            $this->getResponse()->setStatusCode(405);
            $model = new JsonModel(['ok' => false, 'error' => 'POST required.']);
            $model->setTerminal(true);
            return $model;
        }

        if (!$this->userIsAllowed('Omeka\Entity\Item', 'create')
            && !$this->userIsAllowed('Omeka\Entity\Item', 'update')
        ) {
            $this->getResponse()->setStatusCode(403);
            $model = new JsonModel([
                'ok' => false,
                'error' => 'You are not allowed to use the AI assistant.',
            ]);
            $model->setTerminal(true);
            return $model;
        }

        $provider = (string) $this->params()->fromPost('provider', 'dummy');
        $text = trim((string) $this->params()->fromPost('text', ''));
        $availableProperties = $this->parseAvailableProperties((string) $this->params()->fromPost('available_properties', ''));
        $availableTerms = $this->parseAvailableTerms((string) $this->params()->fromPost('available_terms', ''));
        if ($availableTerms === [] && $availableProperties !== []) {
            $availableTerms = array_values(array_unique(array_map(
                static fn(array $property): string => (string) ($property['term'] ?? ''),
                $availableProperties
            )));
            $availableTerms = array_values(array_filter($availableTerms));
        }

        try {
            $pdfSource = $this->extractUploadedPdfText();
        } catch (RuntimeException $e) {
            $this->getResponse()->setStatusCode(400);
            $model = new JsonModel([
                'ok' => false,
                'error' => $e->getMessage(),
            ]);
            $model->setTerminal(true);
            return $model;
        }

        $pdfText = $pdfSource['text'] ?? '';
        $sourceText = $this->buildSourceText($text, $pdfText);

        if ($sourceText === '') {
            $model = new JsonModel(['ok' => false, 'error' => 'No text provided.']);
            $model->setTerminal(true);
            return $model;
        }
        if (mb_strlen($sourceText) > self::MAX_TEXT_LENGTH) {
            $model = new JsonModel([
                'ok' => false,
                'error' => sprintf('Text too long. Maximum is %d characters.', self::MAX_TEXT_LENGTH),
            ]);
            $model->setTerminal(true);
            return $model;
        }

        if ($this->suggestionPipeline->shouldQueue($sourceText, $pdfText !== '')) {
            try {
                $job = $this->jobDispatcher()->dispatch(\OmekaRapper\Job\GenerateSuggestions::class, [
                    'provider' => $provider,
                    'source_text' => $sourceText,
                    'available_terms' => $availableTerms,
                    'available_properties' => $availableProperties,
                    'source' => [
                        'has_pdf' => $pdfText !== '',
                        'ocr_used' => !empty($pdfSource['ocr_used']),
                    ],
                ]);

                $model = new JsonModel([
                    'ok' => true,
                    'queued' => true,
                    'job_id' => $job ? $job->getId() : null,
                    'status' => $job ? $job->getStatus() : 'starting',
                    'provider' => $provider,
                ]);
                $model->setTerminal(true);
                return $model;
            } catch (Throwable $e) {
                $queueWarning = 'Background job dispatch failed. Falling back to inline processing.';
            }
        }

        $result = $this->suggestionPipeline->generate($provider, $sourceText, $availableTerms, $availableProperties);
        $warning = $result['warning'];
        if (!empty($queueWarning ?? null)) {
            $warning = $warning ? $queueWarning . ' ' . $warning : $queueWarning;
        }

        $model = new JsonModel([
            'ok' => true,
            'provider' => $provider,
            'suggestions' => $result['suggestions'],
            'warning' => $warning,
            'source' => [
                'has_pdf' => $pdfText !== '',
                'ocr_used' => !empty($pdfSource['ocr_used']),
                'text_length' => mb_strlen($sourceText),
                'provider_text_length' => $result['provider_text_length'],
                'provider_text_truncated' => $result['provider_text_truncated'],
            ],
        ]);
        $model->setTerminal(true);
        return $model;
    }

    public function statusAction(): JsonModel
    {
        if (!$this->userIsAllowed('Omeka\Entity\Item', 'create')
            && !$this->userIsAllowed('Omeka\Entity\Item', 'update')
        ) {
            $this->getResponse()->setStatusCode(403);
            $model = new JsonModel(['ok' => false, 'error' => 'You are not allowed to use the AI assistant.']);
            $model->setTerminal(true);
            return $model;
        }

        $jobId = (int) $this->params()->fromQuery('job_id', 0);
        if ($jobId < 1) {
            $this->getResponse()->setStatusCode(400);
            $model = new JsonModel(['ok' => false, 'error' => 'job_id is required.']);
            $model->setTerminal(true);
            return $model;
        }

        $job = $this->api()->read('jobs', $jobId)->getContent();
        $status = (string) $job->status();
        $payload = [
            'ok' => true,
            'job_id' => $jobId,
            'status' => $status,
        ];

        if ($status === 'completed') {
            $result = $this->suggestionResultStore->load($jobId);
            if ($result) {
                $payload = array_merge($payload, $result);
            }
        } elseif ($status === 'error' || $status === 'stopped') {
            $payload['error'] = trim((string) $job->log()) ?: 'The suggestion job failed.';
        }

        $model = new JsonModel($payload);
        $model->setTerminal(true);
        return $model;
    }

    public function modelsAction(): JsonModel
    {
        if (!$this->getRequest()->isPost()) {
            $this->getResponse()->setStatusCode(405);
            $model = new JsonModel(['ok' => false, 'error' => 'POST required.']);
            $model->setTerminal(true);
            return $model;
        }

        if (!$this->userIsAllowed('Omeka\Module\Manager', 'configure')) {
            $this->getResponse()->setStatusCode(403);
            $model = new JsonModel([
                'ok' => false,
                'error' => 'You are not allowed to configure module providers.',
            ]);
            $model->setTerminal(true);
            return $model;
        }

        $provider = (string) $this->params()->fromPost('provider', '');
        $baseUrl = trim((string) $this->params()->fromPost('base_url', ''));
        $apiKey = trim((string) $this->params()->fromPost('api_key', ''));

        if ($provider === '' || $baseUrl === '') {
            $model = new JsonModel([
                'ok' => false,
                'error' => 'Provider and base URL are required.',
            ]);
            $model->setTerminal(true);
            return $model;
        }

        try {
            $models = $this->modelCatalog->listModels($provider, $baseUrl, $apiKey);
        } catch (RuntimeException $e) {
            $model = new JsonModel([
                'ok' => false,
                'error' => $e->getMessage(),
            ]);
            $model->setTerminal(true);
            return $model;
        }

        $model = new JsonModel([
            'ok' => true,
            'provider' => $provider,
            'models' => $models,
        ]);
        $model->setTerminal(true);
        return $model;
    }

    private function extractUploadedPdfText(): array
    {
        $files = $this->getRequest()->getFiles()->toArray();
        $upload = $files['pdf'] ?? null;
        if (!is_array($upload) || $upload === [] || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [
                'text' => '',
                'ocr_used' => false,
            ];
        }

        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The PDF upload failed.');
        }

        $tmpName = (string) ($upload['tmp_name'] ?? '');
        $fileName = trim((string) ($upload['name'] ?? ''));
        $size = (int) ($upload['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Uploaded PDF was not received correctly.');
        }

        if ($size < 1) {
            throw new RuntimeException('Uploaded PDF is empty.');
        }

        if ($size > self::MAX_PDF_BYTES) {
            throw new RuntimeException(sprintf(
                'PDF too large. Maximum file size is %d MB.',
                (int) (self::MAX_PDF_BYTES / 1048576)
            ));
        }

        if ($fileName !== '' && !preg_match('/\.pdf$/i', $fileName)) {
            throw new RuntimeException('Only PDF files are supported.');
        }

        return $this->pdfTextExtractor->extract($tmpName);
    }

    private function buildSourceText(string $text, string $pdfText): string
    {
        if ($pdfText === '') {
            return trim($text);
        }

        if ($text === '') {
            return trim($pdfText);
        }

        return trim("User notes:\n{$text}\n\nPDF text:\n{$pdfText}");
    }

    private function parseAvailableTerms(string $rawTerms): array
    {
        if ($rawTerms === '') {
            return [];
        }

        $decoded = json_decode($rawTerms, true);
        if (!is_array($decoded)) {
            return [];
        }

        $terms = [];
        foreach ($decoded as $term) {
            $term = trim((string) $term);
            if ($term !== '') {
                $terms[] = $term;
            }
        }

        return array_values(array_unique($terms));
    }

    private function parseAvailableProperties(string $rawProperties): array
    {
        if ($rawProperties === '') {
            return [];
        }

        $decoded = json_decode($rawProperties, true);
        if (!is_array($decoded)) {
            return [];
        }

        $properties = [];
        foreach ($decoded as $property) {
            if (!is_array($property)) {
                continue;
            }

            $term = trim((string) ($property['term'] ?? ''));
            $label = trim((string) ($property['label'] ?? ''));
            $description = trim((string) ($property['description'] ?? ''));
            if ($term === '') {
                continue;
            }

            $properties[] = [
                'term' => $term,
                'label' => $label,
                'description' => $description,
            ];
        }

        return $properties;
    }
}
