<?php declare(strict_types=1);

namespace OmekaRapper\Job;

use Omeka\Job\AbstractJob;
use OmekaRapper\Service\SuggestionResultStore;
use OmekaRapper\Service\SuggestionPipeline;

class GenerateSuggestions extends AbstractJob
{
    public function perform()
    {
        /** @var SuggestionPipeline $pipeline */
        $pipeline = $this->getServiceLocator()->get(SuggestionPipeline::class);
        /** @var SuggestionResultStore $store */
        $store = $this->getServiceLocator()->get(SuggestionResultStore::class);

        $provider = (string) $this->getArg('provider', 'dummy');
        $sourceText = (string) $this->getArg('source_text', '');
        $availableTerms = $this->getArg('available_terms', []);
        $availableProperties = $this->getArg('available_properties', []);
        $source = $this->getArg('source', []);

        $result = $pipeline->generate(
            $provider,
            $sourceText,
            is_array($availableTerms) ? $availableTerms : [],
            is_array($availableProperties) ? $availableProperties : []
        );
        $store->save($this->job->getId(), [
            'ok' => true,
            'provider' => $result['provider'],
            'suggestions' => $result['suggestions'],
            'warning' => $result['warning'],
            'source' => [
                'has_pdf' => !empty($source['has_pdf']),
                'ocr_used' => !empty($source['ocr_used']),
                'text_length' => mb_strlen($sourceText),
                'provider_text_length' => $result['provider_text_length'],
                'provider_text_truncated' => $result['provider_text_truncated'],
            ],
        ]);
    }
}
