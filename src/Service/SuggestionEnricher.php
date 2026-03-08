<?php declare(strict_types=1);

namespace OmekaRapper\Service;

class SuggestionEnricher
{
    public function enrich(array $suggestions, string $text, array $availableTerms = [], array $availableProperties = []): array
    {
        $heuristic = $this->extractHeuristics($text, $availableTerms, $availableProperties);

        $merged = [
            'title' => $this->firstNonEmpty($suggestions['title'] ?? null, $heuristic['title'] ?? null),
            'creators' => $this->mergeLists($suggestions['creators'] ?? [], $heuristic['creators'] ?? []),
            'date' => $this->firstNonEmpty($suggestions['date'] ?? null, $heuristic['date'] ?? null),
            'publisher' => $this->firstNonEmpty($suggestions['publisher'] ?? null, $heuristic['publisher'] ?? null),
            'publication' => $this->firstNonEmpty($suggestions['publication'] ?? null, $heuristic['publication'] ?? null),
            'abstract' => $this->firstNonEmpty($suggestions['abstract'] ?? null, $heuristic['abstract'] ?? null),
            'subjects' => $this->mergeLists($suggestions['subjects'] ?? [], $heuristic['subjects'] ?? []),
            'identifiers' => $this->mergeLists($suggestions['identifiers'] ?? [], $heuristic['identifiers'] ?? []),
            'language' => $this->firstNonEmpty($suggestions['language'] ?? null, $heuristic['language'] ?? null),
            'contributors' => $this->mergeLists($suggestions['contributors'] ?? [], $heuristic['contributors'] ?? []),
            'type' => $this->firstNonEmpty($suggestions['type'] ?? null, $heuristic['type'] ?? null),
            'extent' => $this->firstNonEmpty($suggestions['extent'] ?? null, $heuristic['extent'] ?? null),
            'rights' => $this->firstNonEmpty($suggestions['rights'] ?? null, $heuristic['rights'] ?? null),
            'spatial' => $this->firstNonEmpty($suggestions['spatial'] ?? null, $heuristic['spatial'] ?? null),
            'temporal' => $this->firstNonEmpty($suggestions['temporal'] ?? null, $heuristic['temporal'] ?? null),
            'relation' => $this->firstNonEmpty($suggestions['relation'] ?? null, $heuristic['relation'] ?? null),
            'source' => $this->firstNonEmpty($suggestions['source'] ?? null, $heuristic['source'] ?? null),
            'alternative_title' => $this->firstNonEmpty($suggestions['alternative_title'] ?? null, $heuristic['alternative_title'] ?? null),
            'format' => $this->firstNonEmpty($suggestions['format'] ?? null, $heuristic['format'] ?? null),
            'confidence' => is_array($suggestions['confidence'] ?? null) ? $suggestions['confidence'] : [],
            'raw' => is_array($suggestions['raw'] ?? null) ? $suggestions['raw'] : [],
        ];

        $existingProperties = is_array($suggestions['properties'] ?? null) ? $suggestions['properties'] : [];
        $merged['properties'] = $this->mergeProperties(
            $existingProperties,
            $heuristic['properties'] ?? [],
            $merged,
            $availableTerms,
            $availableProperties
        );

        return $merged;
    }

    private function extractHeuristics(string $text, array $availableTerms, array $availableProperties): array
    {
        $text = trim($text);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', $text) ?: [])));
        $title = $this->guessTitle($lines);
        $abstract = $this->guessAbstract($text);
        $creators = $this->guessCreators($lines);
        $identifiers = $this->guessIdentifiers($text);
        $date = $this->guessDate($text);
        $publisher = $this->guessLabeledValue($lines, ['publisher', 'published by']);
        $publication = $this->guessLabeledValue($lines, ['journal', 'publication', 'source']);
        $subjects = $this->guessSubjects($lines);
        $language = $this->guessLanguage($text);
        $extra = [
            'contributor' => $this->guessLabeledList($lines, ['contributor', 'contributors', 'editor', 'editors']),
            'type' => $this->guessLabeledValue($lines, ['type', 'resource type', 'document type']),
            'extent' => $this->guessLabeledValue($lines, ['extent', 'pages', 'pagination', 'length']),
            'rights' => $this->guessLabeledValue($lines, ['rights', 'license', 'copyright']),
            'spatial' => $this->guessLabeledValue($lines, ['spatial', 'place', 'location', 'coverage']),
            'temporal' => $this->guessLabeledValue($lines, ['temporal', 'period']),
            'relation' => $this->guessLabeledValue($lines, ['relation', 'related']),
            'source' => $this->guessLabeledValue($lines, ['source']),
            'alternative_title' => $this->guessLabeledValue($lines, ['alternative title', 'alt title']),
            'format' => $this->guessLabeledValue($lines, ['format', 'medium']),
        ];

        $properties = $this->buildProperties([
            'title' => $title,
            'creators' => $creators,
            'date' => $date,
            'publisher' => $publisher,
            'publication' => $publication,
            'abstract' => $abstract,
            'subjects' => $subjects,
            'identifiers' => $identifiers,
            'language' => $language,
            'contributors' => $extra['contributor'] ?? [],
            'type' => $extra['type'] ?? null,
            'extent' => $extra['extent'] ?? null,
            'rights' => $extra['rights'] ?? null,
            'spatial' => $extra['spatial'] ?? null,
            'temporal' => $extra['temporal'] ?? null,
            'relation' => $extra['relation'] ?? null,
            'source' => $extra['source'] ?? null,
            'alternative_title' => $extra['alternative_title'] ?? null,
            'format' => $extra['format'] ?? null,
            'extra' => $extra,
        ], $availableTerms, $availableProperties);

        return [
            'title' => $title,
            'creators' => $creators,
            'date' => $date,
            'publisher' => $publisher,
            'publication' => $publication,
            'abstract' => $abstract,
            'subjects' => $subjects,
            'identifiers' => $identifiers,
            'language' => $language,
            'contributors' => $extra['contributor'] ?? [],
            'type' => $extra['type'] ?? null,
            'extent' => $extra['extent'] ?? null,
            'rights' => $extra['rights'] ?? null,
            'spatial' => $extra['spatial'] ?? null,
            'temporal' => $extra['temporal'] ?? null,
            'relation' => $extra['relation'] ?? null,
            'source' => $extra['source'] ?? null,
            'alternative_title' => $extra['alternative_title'] ?? null,
            'format' => $extra['format'] ?? null,
            'extra' => $extra,
            'properties' => $properties,
        ];
    }

    private function guessTitle(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (mb_strlen($line) >= 8 && mb_strlen($line) <= 180) {
                return $line;
            }
        }
        return null;
    }

    private function guessAbstract(string $text): ?string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
        if ($text === '') {
            return null;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [];
        $sentences = array_values(array_filter(array_map('trim', $sentences)));
        if ($sentences === []) {
            return mb_substr($text, 0, 400);
        }

        return trim(implode(' ', array_slice($sentences, 0, 3)));
    }

    private function guessCreators(array $lines): array
    {
        foreach ($lines as $line) {
            if (preg_match('/^(?:by|author|authors)\s*:\s*(.+)$/i', $line, $matches)) {
                return $this->splitNames($matches[1]);
            }
        }
        return [];
    }

    private function splitNames(string $value): array
    {
        $parts = preg_split('/\s*(?:,|;| and )\s*/i', trim($value)) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts)));
        return array_values(array_unique($parts));
    }

    private function guessIdentifiers(string $text): array
    {
        $identifiers = [];

        if (preg_match_all('/10\.\d{4,9}\/[-._;()\/:A-Z0-9]+/i', $text, $matches)) {
            $identifiers = array_merge($identifiers, $matches[0]);
        }
        if (preg_match_all('/\b97[89][-\s]?\d[-\s]?\d{2,5}[-\s]?\d{2,7}[-\s]?\d{1,7}[-\s]?[\dX]\b/i', $text, $matches)) {
            $identifiers = array_merge($identifiers, $matches[0]);
        }
        if (preg_match_all('/\bISSN[:\s]*\d{4}-\d{3}[\dX]\b/i', $text, $matches)) {
            $identifiers = array_merge($identifiers, $matches[0]);
        }

        $identifiers = array_values(array_unique(array_map('trim', $identifiers)));
        return array_values(array_filter($identifiers));
    }

    private function guessDate(string $text): ?string
    {
        if (preg_match('/\b(19|20)\d{2}\b/', $text, $matches)) {
            return $matches[0];
        }
        return null;
    }

    private function guessLabeledValue(array $lines, array $labels): ?string
    {
        foreach ($lines as $line) {
            foreach ($labels as $label) {
                if (preg_match(sprintf('/^%s\s*:\s*(.+)$/i', preg_quote($label, '/')), $line, $matches)) {
                    return trim($matches[1]);
                }
            }
        }
        return null;
    }

    private function guessLabeledList(array $lines, array $labels): array
    {
        $value = $this->guessLabeledValue($lines, $labels);
        return $value !== null ? $this->splitNames($value) : [];
    }

    private function guessSubjects(array $lines): array
    {
        foreach ($lines as $line) {
            if (preg_match('/^(?:keywords|subjects?)\s*:\s*(.+)$/i', $line, $matches)) {
                $parts = preg_split('/\s*(?:,|;)\s*/', trim($matches[1])) ?: [];
                $parts = array_values(array_filter(array_map('trim', $parts)));
                return array_values(array_unique($parts));
            }
        }
        return [];
    }

    private function guessLanguage(string $text): ?string
    {
        if (preg_match('/\b(the|and|for|with|from|this|that)\b/i', $text)) {
            return 'English';
        }
        return null;
    }

    private function buildProperties(array $data, array $availableTerms, array $availableProperties = []): array
    {
        return MetadataFieldMapper::buildPropertySuggestions($data, $availableTerms, $availableProperties);
    }

    private function mergeProperties(array $existing, array $heuristic, array $merged, array $availableTerms, array $availableProperties): array
    {
        $map = [];
        foreach ([$existing, $heuristic, $this->buildProperties($merged, $availableTerms, $availableProperties)] as $properties) {
            foreach ($properties as $property) {
                if (!is_array($property)) {
                    continue;
                }
                $term = trim((string) ($property['term'] ?? ''));
                $values = $this->listify($property['values'] ?? []);
                if ($term === '' || $values === []) {
                    continue;
                }
                $map[$term] = array_values(array_unique(array_merge($map[$term] ?? [], $values)));
            }
        }

        $properties = [];
        foreach ($map as $term => $values) {
            $properties[] = ['term' => $term, 'values' => $values];
        }
        return $properties;
    }

    private function firstNonEmpty(?string $primary, ?string $fallback): ?string
    {
        $primary = $this->normalizeString($primary);
        if ($primary !== null) {
            return $primary;
        }
        return $this->normalizeString($fallback);
    }

    private function mergeLists(array $primary, array $fallback): array
    {
        return array_values(array_unique(array_merge($this->listify($primary), $this->listify($fallback))));
    }

    private function listify(mixed $value): array
    {
        if (is_array($value)) {
            $values = [];
            foreach ($value as $entry) {
                $entry = $this->normalizeString((string) $entry);
                if ($entry !== null) {
                    $values[] = $entry;
                }
            }
            return array_values(array_unique($values));
        }

        $normalized = $this->normalizeString((string) $value);
        return $normalized !== null ? [$normalized] : [];
    }

    private function normalizeString(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
