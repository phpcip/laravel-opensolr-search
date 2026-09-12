<?php

namespace App\Search;

use Illuminate\Support\Str;
use Opensolr\ScoutOpensolr\OpensolrClient;

/**
 * Search the configured Opensolr index through the platform's tuned hybrid pipeline.
 *
 * One call to embed_and_search embeds the query server-side, fuses BM25 and kNN scores per
 * document, highlights, facets and spell-checks, and honours the Search Tuning saved for the
 * index in the Control Panel. This class reduces the response to what the page needs: the
 * raw Solr parameters, the query vector and the debug block never leave the server.
 */
class IndexSearch
{
    public const ROWS = 10;

    public const MAX_PAGES = 100;

    /**
     * Facet fields the platform returns, under the names the page shows.
     */
    protected const FACET_NAMES = [
        'meta_detected_language' => 'language',
        'meta_og_locale' => 'locale',
    ];

    /**
     * Prefix of the synthetic URI the Data Ingestion API assigns to documents that have no
     * real address, such as Eloquent models pushed by Scout. Those are shown without a link.
     */
    protected const INGEST_URI = 'https://ingest.opensolr.com/';

    public function __construct(protected OpensolrClient $client, protected string $index) {}

    /**
     * Run one page of results.
     *
     * The hard freshness window (fresh=yes) is always off; the visitor's "fresh first" toggle
     * maps to the fresh_bias tuning knob, which re-orders by recency without hiding anything.
     *
     * @return array{query: string, total: int, page: int, pages: int, qtime: int, suggestion: ?string, facets: list<array>, docs: list<array>}
     */
    public function search(string $query, int $page = 1, bool $fresh = false): array
    {
        $params = [
            'start' => ($page - 1) * self::ROWS,
            'in' => 'all',
            'fresh' => 'no',
        ];
        if ($fresh) {
            $params['fresh_bias'] = 1;
        }

        $body = $this->client->embedAndSearch($this->index, $query, self::ROWS, $params);
        $results = is_array($body['results'] ?? null) ? $body['results'] : [];
        $total = (int) ($results['num'] ?? 0);
        $highlights = is_array($results['hl'] ?? null) ? $results['hl'] : [];
        $docs = array_values(array_filter((array) ($results['docs'] ?? []), 'is_array'));

        return [
            'query' => $query,
            'total' => $total,
            'page' => $page,
            'pages' => min(self::MAX_PAGES, (int) ceil($total / self::ROWS)),
            'qtime' => (int) ($results['qtime'] ?? 0),
            'suggestion' => self::suggestion($results['spellcheck'] ?? []),
            'facets' => self::facets($results['facets'] ?? []),
            'docs' => array_map(fn (array $doc) => self::document($doc, $highlights), $docs),
        ];
    }

    /**
     * Reduce one Solr document to the fields the result card shows.
     */
    protected static function document(array $doc, array $highlights): array
    {
        $id = self::scalar($doc['id'] ?? '');
        $hl = is_array($highlights[$id] ?? null) ? $highlights[$id] : [];
        $title = self::scalar($doc['title'] ?? '') ?: 'Untitled';
        $description = self::scalar($doc['description'] ?? '');
        $snippet = self::fragment($hl, 'description') ?? self::fragment($hl, 'text') ?? Str::limit($description, 300, '…');

        return [
            'id' => $id,
            'title' => $title,
            'title_segments' => Highlight::segments(self::fragment($hl, 'title') ?? $title),
            'snippet_segments' => Highlight::segments($snippet),
            'url' => self::publicUrl(self::scalar($doc['uri'] ?? '')),
            'domain' => self::scalar($doc['meta_domain'] ?? ''),
            'section' => self::scalar($doc['meta_article_section'] ?? $doc['meta_category'] ?? ''),
            'language' => self::scalar($doc['meta_detected_language'] ?? ''),
            'date' => self::date(self::scalar($doc['creation_date'] ?? '')),
            'image' => self::publicUrl(self::scalar($doc['og_image'] ?? '')),
            'score' => round((float) ($doc['score'] ?? 0), 3),
        ];
    }

    /**
     * First highlight fragment for a field, or null when Solr produced none.
     */
    protected static function fragment(array $hl, string $field): ?string
    {
        $fragments = $hl[$field] ?? null;
        if (is_array($fragments)) {
            $fragments = $fragments[0] ?? null;
        }

        return is_string($fragments) && trim(Highlight::plain($fragments)) !== '' ? $fragments : null;
    }

    /**
     * Only absolute http(s) addresses are handed to the browser, and never the synthetic
     * ingestion URI. Anything else, including javascript: and data: schemes, becomes null.
     */
    protected static function publicUrl(string $value): ?string
    {
        if ($value === '' || str_starts_with($value, self::INGEST_URI)) {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : null;
    }

    /**
     * Solr dates arrive as ISO 8601 in UTC; the page shows mm/dd/yyyy.
     */
    protected static function date(string $value): ?string
    {
        $time = $value !== '' ? strtotime($value) : false;

        return $time === false ? null : gmdate('m/d/Y', $time);
    }

    /**
     * Solr multi-valued fields come back as arrays; the page wants the first value as text.
     */
    protected static function scalar(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Pair up Solr's facet counts, which arrive either flat ([value, count, …]) or as a map.
     *
     * @return list<array{field: string, values: list<array{value: string, count: int}>}>
     */
    protected static function facets(mixed $facets): array
    {
        $out = [];
        foreach ((array) $facets as $field => $counts) {
            if (! is_array($counts) || $counts === []) {
                continue;
            }
            $pairs = array_is_list($counts) ? array_chunk($counts, 2) : array_map(null, array_keys($counts), $counts);
            $values = [];
            foreach ($pairs as $pair) {
                if (count($pair) === 2 && is_scalar($pair[0])) {
                    $values[] = ['value' => (string) $pair[0], 'count' => (int) $pair[1]];
                }
            }
            if ($values !== []) {
                $name = self::FACET_NAMES[$field] ?? Str::after((string) $field, 'meta_');
                $out[] = ['field' => $name, 'values' => $values];
            }
        }

        return $out;
    }

    /**
     * The spell-checker's best collation, if it found one, as a plain query string.
     */
    protected static function suggestion(mixed $spellcheck): ?string
    {
        $collations = is_array($spellcheck) ? ($spellcheck['collations'] ?? []) : [];
        $previous = null;
        foreach ((array) $collations as $item) {
            if (is_array($item) && is_string($item['collationQuery'] ?? null)) {
                return trim($item['collationQuery']);
            }
            if (is_string($item) && $previous === 'collation') {
                return trim($item);
            }
            $previous = $item;
        }

        return null;
    }
}
