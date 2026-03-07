<?php declare(strict_types=1);

namespace OmekaRapper\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OmekaRapper\Service\AiClientManager;
use OmekaRapper\Service\Provider\AnthropicProvider;
use OmekaRapper\Service\Provider\DummyProvider;
use OmekaRapper\Service\Provider\OpenAiProvider;

class AiClientManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AiClientManager
    {
        $settings = $container->get('Omeka\Settings');

        $providers = [
            new DummyProvider(),
        ];

        $openAiEnabled = (bool) $settings->get('omekarapper_openai_enabled', false);
        $openAiApiKey = trim((string) $settings->get('omekarapper_openai_api_key', ''));
        if ($openAiEnabled && $openAiApiKey !== '') {
            $providers[] = new OpenAiProvider(
                'chatgpt',
                'ChatGPT',
                trim((string) $settings->get('omekarapper_openai_model', 'gpt-5-mini')) ?: 'gpt-5-mini',
                trim((string) $settings->get('omekarapper_openai_base_url', 'https://api.openai.com/v1/responses')) ?: 'https://api.openai.com/v1/responses',
                $openAiApiKey,
                'responses',
                true
            );
        }

        $codexEnabled = (bool) $settings->get('omekarapper_codex_enabled', false);
        if ($codexEnabled && $openAiApiKey !== '') {
            $providers[] = new OpenAiProvider(
                'codex',
                'Codex',
                trim((string) $settings->get('omekarapper_codex_model', 'codex-mini-latest')) ?: 'codex-mini-latest',
                trim((string) $settings->get('omekarapper_codex_base_url', 'https://api.openai.com/v1/responses')) ?: 'https://api.openai.com/v1/responses',
                $openAiApiKey,
                'responses',
                true
            );
        }

        $anthropicEnabled = (bool) $settings->get('omekarapper_anthropic_enabled', false);
        $anthropicApiKey = trim((string) $settings->get('omekarapper_anthropic_api_key', ''));
        if ($anthropicEnabled && $anthropicApiKey !== '') {
            $providers[] = new AnthropicProvider(
                'claude',
                'Claude',
                $anthropicApiKey,
                trim((string) $settings->get('omekarapper_anthropic_model', 'claude-sonnet-4-0')) ?: 'claude-sonnet-4-0',
                trim((string) $settings->get('omekarapper_anthropic_base_url', 'https://api.anthropic.com/v1/messages')) ?: 'https://api.anthropic.com/v1/messages'
            );
        }

        $localEnabled = (bool) $settings->get('omekarapper_local_enabled', false);
        if ($localEnabled) {
            $providers[] = new OpenAiProvider(
                'ollama',
                'Ollama',
                trim((string) $settings->get('omekarapper_local_model', 'qwen2.5:7b')) ?: 'qwen2.5:7b',
                trim((string) $settings->get('omekarapper_local_base_url', 'http://localhost:11434/v1/chat/completions')) ?: 'http://localhost:11434/v1/chat/completions',
                trim((string) $settings->get('omekarapper_local_api_key', 'ollama')),
                'chat',
                false
            );
        }

        return new AiClientManager($providers);
    }
}
