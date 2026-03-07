<?php declare(strict_types=1);

namespace OmekaRapper;

use Omeka\Module\AbstractModule;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    private const SETTINGS = [
        'omekarapper_provider_default' => 'dummy',
        'omekarapper_openai_enabled' => false,
        'omekarapper_openai_api_key' => '',
        'omekarapper_openai_model' => 'gpt-5-mini',
        'omekarapper_openai_base_url' => 'https://api.openai.com/v1/responses',
        'omekarapper_codex_enabled' => false,
        'omekarapper_codex_model' => 'codex-mini-latest',
        'omekarapper_codex_base_url' => 'https://api.openai.com/v1/responses',
        'omekarapper_anthropic_enabled' => false,
        'omekarapper_anthropic_api_key' => '',
        'omekarapper_anthropic_model' => 'claude-sonnet-4-0',
        'omekarapper_anthropic_base_url' => 'https://api.anthropic.com/v1/messages',
        'omekarapper_local_enabled' => false,
        'omekarapper_local_api_key' => 'ollama',
        'omekarapper_local_model' => 'qwen2.5:7b',
        'omekarapper_local_base_url' => 'http://localhost:11434/v1/chat/completions',
        'omekarapper_provider_timeout' => 25,
        'omekarapper_pdftotext_path' => '',
        'omekarapper_pdftoppm_path' => '',
        'omekarapper_tesseract_path' => '',
    ];

    public function getConfig(): array
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install($serviceLocator): void
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        foreach (self::SETTINGS as $key => $value) {
            $settings->set($key, $value);
        }
    }

    public function uninstall($serviceLocator): void
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        foreach (array_keys(self::SETTINGS) as $key) {
            $settings->delete($key);
        }
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        return $renderer->partial('omeka-rapper/admin/module/config-form', [
            'settings' => [
                'provider_default' => (string) $settings->get('omekarapper_provider_default', self::SETTINGS['omekarapper_provider_default']),
                'openai_enabled' => (bool) $settings->get('omekarapper_openai_enabled', self::SETTINGS['omekarapper_openai_enabled']),
                'openai_api_key' => (string) $settings->get('omekarapper_openai_api_key', self::SETTINGS['omekarapper_openai_api_key']),
                'openai_model' => (string) $settings->get('omekarapper_openai_model', self::SETTINGS['omekarapper_openai_model']),
                'openai_base_url' => (string) $settings->get('omekarapper_openai_base_url', self::SETTINGS['omekarapper_openai_base_url']),
                'codex_enabled' => (bool) $settings->get('omekarapper_codex_enabled', self::SETTINGS['omekarapper_codex_enabled']),
                'codex_model' => (string) $settings->get('omekarapper_codex_model', self::SETTINGS['omekarapper_codex_model']),
                'codex_base_url' => (string) $settings->get('omekarapper_codex_base_url', self::SETTINGS['omekarapper_codex_base_url']),
                'anthropic_enabled' => (bool) $settings->get('omekarapper_anthropic_enabled', self::SETTINGS['omekarapper_anthropic_enabled']),
                'anthropic_api_key' => (string) $settings->get('omekarapper_anthropic_api_key', self::SETTINGS['omekarapper_anthropic_api_key']),
                'anthropic_model' => (string) $settings->get('omekarapper_anthropic_model', self::SETTINGS['omekarapper_anthropic_model']),
                'anthropic_base_url' => (string) $settings->get('omekarapper_anthropic_base_url', self::SETTINGS['omekarapper_anthropic_base_url']),
                'local_enabled' => (bool) $settings->get('omekarapper_local_enabled', self::SETTINGS['omekarapper_local_enabled']),
                'local_api_key' => (string) $settings->get('omekarapper_local_api_key', self::SETTINGS['omekarapper_local_api_key']),
                'local_model' => (string) $settings->get('omekarapper_local_model', self::SETTINGS['omekarapper_local_model']),
                'local_base_url' => (string) $settings->get('omekarapper_local_base_url', self::SETTINGS['omekarapper_local_base_url']),
                'provider_timeout' => (int) $settings->get('omekarapper_provider_timeout', self::SETTINGS['omekarapper_provider_timeout']),
                'php_max_execution_time' => (int) ini_get('max_execution_time'),
                'pdftotext_path' => (string) $settings->get('omekarapper_pdftotext_path', self::SETTINGS['omekarapper_pdftotext_path']),
                'pdftoppm_path' => (string) $settings->get('omekarapper_pdftoppm_path', self::SETTINGS['omekarapper_pdftoppm_path']),
                'tesseract_path' => (string) $settings->get('omekarapper_tesseract_path', self::SETTINGS['omekarapper_tesseract_path']),
            ],
        ]);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $data = $controller->params()->fromPost();
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        $providerDefault = in_array(($data['omekarapper_provider_default'] ?? 'dummy'), ['dummy', 'chatgpt', 'codex', 'claude', 'ollama'], true)
            ? (string) $data['omekarapper_provider_default']
            : 'dummy';

        $settings->set('omekarapper_provider_default', $providerDefault);
        $settings->set('omekarapper_openai_enabled', !empty($data['omekarapper_openai_enabled']));
        $settings->set('omekarapper_openai_api_key', trim((string) ($data['omekarapper_openai_api_key'] ?? '')));
        $settings->set('omekarapper_openai_model', trim((string) ($data['omekarapper_openai_model'] ?? self::SETTINGS['omekarapper_openai_model'])) ?: self::SETTINGS['omekarapper_openai_model']);
        $settings->set('omekarapper_openai_base_url', trim((string) ($data['omekarapper_openai_base_url'] ?? self::SETTINGS['omekarapper_openai_base_url'])) ?: self::SETTINGS['omekarapper_openai_base_url']);
        $settings->set('omekarapper_codex_enabled', !empty($data['omekarapper_codex_enabled']));
        $settings->set('omekarapper_codex_model', trim((string) ($data['omekarapper_codex_model'] ?? self::SETTINGS['omekarapper_codex_model'])) ?: self::SETTINGS['omekarapper_codex_model']);
        $settings->set('omekarapper_codex_base_url', trim((string) ($data['omekarapper_codex_base_url'] ?? self::SETTINGS['omekarapper_codex_base_url'])) ?: self::SETTINGS['omekarapper_codex_base_url']);
        $settings->set('omekarapper_anthropic_enabled', !empty($data['omekarapper_anthropic_enabled']));
        $settings->set('omekarapper_anthropic_api_key', trim((string) ($data['omekarapper_anthropic_api_key'] ?? '')));
        $settings->set('omekarapper_anthropic_model', trim((string) ($data['omekarapper_anthropic_model'] ?? self::SETTINGS['omekarapper_anthropic_model'])) ?: self::SETTINGS['omekarapper_anthropic_model']);
        $settings->set('omekarapper_anthropic_base_url', trim((string) ($data['omekarapper_anthropic_base_url'] ?? self::SETTINGS['omekarapper_anthropic_base_url'])) ?: self::SETTINGS['omekarapper_anthropic_base_url']);
        $settings->set('omekarapper_local_enabled', !empty($data['omekarapper_local_enabled']));
        $settings->set('omekarapper_local_api_key', trim((string) ($data['omekarapper_local_api_key'] ?? self::SETTINGS['omekarapper_local_api_key'])));
        $settings->set('omekarapper_local_model', trim((string) ($data['omekarapper_local_model'] ?? self::SETTINGS['omekarapper_local_model'])) ?: self::SETTINGS['omekarapper_local_model']);
        $settings->set('omekarapper_local_base_url', trim((string) ($data['omekarapper_local_base_url'] ?? self::SETTINGS['omekarapper_local_base_url'])) ?: self::SETTINGS['omekarapper_local_base_url']);
        $providerTimeout = (int) ($data['omekarapper_provider_timeout'] ?? self::SETTINGS['omekarapper_provider_timeout']);
        $providerTimeout = max(1, $providerTimeout);
        $settings->set('omekarapper_provider_timeout', $providerTimeout);
        $settings->set('omekarapper_pdftotext_path', trim((string) ($data['omekarapper_pdftotext_path'] ?? self::SETTINGS['omekarapper_pdftotext_path'])));
        $settings->set('omekarapper_pdftoppm_path', trim((string) ($data['omekarapper_pdftoppm_path'] ?? self::SETTINGS['omekarapper_pdftoppm_path'])));
        $settings->set('omekarapper_tesseract_path', trim((string) ($data['omekarapper_tesseract_path'] ?? self::SETTINGS['omekarapper_tesseract_path'])));

        return true;
    }

    public function attachListeners(SharedEventManagerInterface $shared): void
    {
        // Inject the panel on admin item add/edit pages.
        $shared->attach(
            'Omeka\Controller\Admin\Item',
            'view.add.after',
            [$this, 'injectPanel']
        );

        $shared->attach(
            'Omeka\Controller\Admin\Item',
            'view.edit.after',
            [$this, 'injectPanel']
        );
    }

    public function injectPanel($event): void
    {
        $view = $event->getTarget();
        $scriptPath = __DIR__ . '/asset/js/omeka-rapper.js';
        $scriptUrl = $view->assetUrl('js/omeka-rapper.js', 'OmekaRapper', false, false);
        if (is_file($scriptPath)) {
            $separator = str_contains($scriptUrl, '?') ? '&' : '?';
            $scriptUrl .= $separator . 'v=' . filemtime($scriptPath);
        }

        // Load JS
        $view->headScript()->appendFile(
            $scriptUrl
        );

        // Render panel
        echo $view->partial('omeka-rapper/admin/assist/panel');
    }
}
