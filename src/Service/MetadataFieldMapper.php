<?php declare(strict_types=1);

namespace OmekaRapper\Service;

class MetadataFieldMapper
{
    private const SLOT_KEYWORDS = [
        'alternative_title' => ['alternative title', 'alt title', 'variant title', 'other title'],
        'title' => ['title', 'name'],
        'creators' => ['creator', 'author', 'authors'],
        'contributors' => ['contributor', 'contributors', 'editor', 'editors'],
        'date' => ['date', 'issued', 'created', 'publication date'],
        'publisher' => ['publisher', 'published by'],
        'publication' => ['publication', 'journal', 'container', 'series', 'is part of', 'source title'],
        'abstract' => ['abstract', 'description', 'summary'],
        'subjects' => ['subject', 'subjects', 'keyword', 'keywords', 'topic', 'topics'],
        'identifiers' => ['identifier', 'identifiers', 'doi', 'isbn', 'issn', 'call number', 'accession number'],
        'language' => ['language'],
        'type' => ['type', 'genre', 'resource type', 'document type'],
        'extent' => ['extent', 'pages', 'pagination', 'duration', 'length'],
        'rights' => ['rights', 'license', 'copyright'],
        'spatial' => ['spatial', 'place', 'location', 'geographic', 'coverage'],
        'temporal' => ['temporal', 'period', 'chronological'],
        'relation' => ['relation', 'related'],
        'source' => ['source', 'derived from', 'original source'],
        'format' => ['format', 'medium', 'file format', 'mime'],
    ];

    private const SLOT_TO_TERMS = [
        'title' => ['dcterms:title'],
        'alternative_title' => ['dcterms:alternative'],
        'creators' => ['dcterms:creator'],
        'contributors' => ['dcterms:contributor'],
        'date' => ['dcterms:date'],
        'publisher' => ['dcterms:publisher'],
        'publication' => ['dcterms:isPartOf'],
        'abstract' => ['dcterms:abstract', 'dcterms:description'],
        'subjects' => ['dcterms:subject'],
        'identifiers' => ['dcterms:identifier'],
        'language' => ['dcterms:language'],
        'type' => ['dcterms:type'],
        'extent' => ['dcterms:extent'],
        'rights' => ['dcterms:rights'],
        'spatial' => ['dcterms:spatial'],
        'temporal' => ['dcterms:temporal'],
        'relation' => ['dcterms:relation'],
        'source' => ['dcterms:source'],
        'format' => ['dcterms:format'],
    ];

    public static function buildPropertySuggestions(array $data, array $availableTerms = [], array $availableProperties = []): array
    {
        $map = [];
        $availableTermSet = self::normalizeAvailableTermSet($availableTerms, $availableProperties);
        $slotValues = self::slotValues($data);

        foreach (self::SLOT_TO_TERMS as $slot => $terms) {
            $values = $slotValues[$slot] ?? [];
            if ($values === []) {
                continue;
            }

            foreach ($terms as $term) {
                if ($availableTermSet !== [] && !isset($availableTermSet[$term])) {
                    continue;
                }
                if ($term === 'dcterms:description'
                    && isset($availableTermSet['dcterms:abstract'])
                    && ($slotValues['abstract'] ?? []) !== []
                ) {
                    continue;
                }
                $map[$term] = array_values(array_unique(array_merge($map[$term] ?? [], $values)));
            }
        }

        foreach (self::normalizeAvailableProperties($availableProperties) as $property) {
            $slot = self::inferSlotForProperty($property);
            if ($slot === null) {
                continue;
            }

            $values = $slotValues[$slot] ?? [];
            if ($values === []) {
                continue;
            }

            $term = $property['term'];
            $map[$term] = array_values(array_unique(array_merge($map[$term] ?? [], $values)));
        }

        $normalized = [];
        foreach ($map as $term => $values) {
            if ($values === []) {
                continue;
            }
            $normalized[] = ['term' => $term, 'values' => $values];
        }

        return $normalized;
    }

    public static function normalizeAvailableProperties(array $availableProperties): array
    {
        $normalized = [];

        foreach ($availableProperties as $property) {
            if (!is_array($property)) {
                continue;
            }

            $term = trim((string) ($property['term'] ?? ''));
            if ($term === '') {
                continue;
            }

            $normalized[$term] = [
                'term' => $term,
                'label' => trim((string) ($property['label'] ?? '')),
                'description' => trim((string) ($property['description'] ?? '')),
            ];
        }

        return array_values($normalized);
    }

    public static function normalizeAvailableTermSet(array $availableTerms, array $availableProperties = []): array
    {
        $set = [];

        foreach ($availableTerms as $term) {
            $term = trim((string) $term);
            if ($term !== '') {
                $set[$term] = true;
            }
        }

        foreach (self::normalizeAvailableProperties($availableProperties) as $property) {
            $set[$property['term']] = true;
        }

        return $set;
    }

    public static function inferSlotForProperty(array $property): ?string
    {
        $term = trim((string) ($property['term'] ?? ''));
        $label = self::normalizeSearchText((string) ($property['label'] ?? ''));
        $description = self::normalizeSearchText((string) ($property['description'] ?? ''));
        $haystack = trim($label . ' ' . $description);

        foreach (self::SLOT_TO_TERMS as $slot => $terms) {
            if (in_array($term, $terms, true)) {
                return $slot;
            }
        }

        if ($haystack === '') {
            return null;
        }

        foreach (self::SLOT_KEYWORDS as $slot => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, self::normalizeSearchText($keyword))) {
                    return $slot;
                }
            }
        }

        return null;
    }

    private static function slotValues(array $data): array
    {
        $extra = is_array($data['extra'] ?? null) ? $data['extra'] : [];

        return [
            'title' => self::listify($data['title'] ?? null),
            'alternative_title' => self::listify($data['alternative_title'] ?? ($extra['alternative_title'] ?? null)),
            'creators' => self::listify($data['creators'] ?? []),
            'contributors' => self::listify($data['contributors'] ?? ($extra['contributor'] ?? [])),
            'date' => self::listify($data['date'] ?? null),
            'publisher' => self::listify($data['publisher'] ?? null),
            'publication' => self::listify($data['publication'] ?? null),
            'abstract' => self::listify($data['abstract'] ?? null),
            'subjects' => self::listify($data['subjects'] ?? []),
            'identifiers' => self::listify($data['identifiers'] ?? []),
            'language' => self::listify($data['language'] ?? null),
            'type' => self::listify($data['type'] ?? ($extra['type'] ?? null)),
            'extent' => self::listify($data['extent'] ?? ($extra['extent'] ?? null)),
            'rights' => self::listify($data['rights'] ?? ($extra['rights'] ?? null)),
            'spatial' => self::listify($data['spatial'] ?? ($extra['spatial'] ?? null)),
            'temporal' => self::listify($data['temporal'] ?? ($extra['temporal'] ?? null)),
            'relation' => self::listify($data['relation'] ?? ($extra['relation'] ?? null)),
            'source' => self::listify($data['source'] ?? ($extra['source'] ?? null)),
            'format' => self::listify($data['format'] ?? ($extra['format'] ?? null)),
        ];
    }

    private static function listify(mixed $value): array
    {
        if (is_array($value)) {
            $values = [];
            foreach ($value as $entry) {
                $entry = trim((string) $entry);
                if ($entry !== '') {
                    $values[] = $entry;
                }
            }
            return array_values(array_unique($values));
        }

        $value = trim((string) $value);
        return $value !== '' ? [$value] : [];
    }

    private static function normalizeSearchText(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        return trim($value);
    }
}
