<?php declare(strict_types=1);

namespace OmekaRapper\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use OmekaRapper\Service\AiClientManager;
use RuntimeException;

class AssistController extends AbstractActionController
{
    private const MAX_TEXT_LENGTH = 50000;

    public function __construct(private AiClientManager $clients) {}

    public function indexAction(): JsonModel
    {
        return new JsonModel([
            'ok' => true,
            'module' => 'OmekaRapper',
            'message' => 'Use /admin/omeka-rapper/providers and POST /admin/omeka-rapper/suggest',
        ]);
    }

    public function providersAction(): JsonModel
    {
        if (!$this->userIsAllowed('Omeka\Entity\Item', 'create')
            && !$this->userIsAllowed('Omeka\Entity\Item', 'update')
        ) {
            $this->getResponse()->setStatusCode(403);
            return new JsonModel([
                'ok' => false,
                'error' => 'You are not allowed to use the AI assistant.',
            ]);
        }

        return new JsonModel([
            'ok' => true,
            'providers' => $this->clients->listProviderNames(),
        ]);
    }

    public function suggestAction(): JsonModel
    {
        if (!$this->getRequest()->isPost()) {
            $this->getResponse()->setStatusCode(405);
            return new JsonModel(['ok' => false, 'error' => 'POST required.']);
        }

        if (!$this->userIsAllowed('Omeka\Entity\Item', 'create')
            && !$this->userIsAllowed('Omeka\Entity\Item', 'update')
        ) {
            $this->getResponse()->setStatusCode(403);
            return new JsonModel([
                'ok' => false,
                'error' => 'You are not allowed to use the AI assistant.',
            ]);
        }

        $provider = (string) $this->params()->fromPost('provider', 'dummy');
        $text = trim((string) $this->params()->fromPost('text', ''));

        if ($text === '') {
            return new JsonModel(['ok' => false, 'error' => 'No text provided.']);
        }
        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            return new JsonModel([
                'ok' => false,
                'error' => sprintf('Text too long. Maximum is %d characters.', self::MAX_TEXT_LENGTH),
            ]);
        }

        try {
            $client = $this->clients->get($provider);
            $suggestions = $client->suggestCatalogMetadata([
                'text' => $text,
            ]);
        } catch (RuntimeException $e) {
            return new JsonModel([
                'ok' => false,
                'error' => $e->getMessage(),
            ]);
        }

        return new JsonModel([
            'ok' => true,
            'provider' => $provider,
            'suggestions' => $suggestions,
        ]);
    }
}
