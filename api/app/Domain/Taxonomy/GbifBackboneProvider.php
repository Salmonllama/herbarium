<?php

namespace App\Domain\Taxonomy;

use App\Contracts\TaxonLookupProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GbifBackboneProvider implements TaxonLookupProvider
{
    public function name(): string
    {
        return 'gbif';
    }

    public function search(string $query, int $limit = 10): array
    {
        $normalized = strtolower(trim($query));
        if ($normalized === '') {
            return [];
        }

        $cacheKey = 'herbarium:taxonomy:gbif:'.md5($normalized.'|'.$limit);
        $ttl = (int) config('herbarium.taxonomy.cache_ttl_seconds', 86400);

        return Cache::remember($cacheKey, $ttl, function () use ($query, $limit) {
            try {
                $baseUrl = rtrim((string) config('herbarium.taxonomy.gbif_base_url', 'https://api.gbif.org/v1'), '/');
                $response = Http::timeout(8)
                    ->acceptJson()
                    ->get("{$baseUrl}/species/suggest", [
                        'q' => $query,
                        'limit' => min(25, max(1, $limit)),
                        'datasetKey' => 'd7dddbf4-2cf0-4f39-9b2a-bb099caae36c', // GBIF Backbone Taxonomy
                    ]);

                if (! $response->successful()) {
                    return [];
                }

                $items = $response->json();
                if (! is_array($items)) {
                    return [];
                }

                return collect($items)
                    ->filter(fn ($row) => is_array($row) && ! empty($row['canonicalName'] ?? $row['scientificName']))
                    ->map(function (array $row) {
                        $canonical = trim((string) ($row['canonicalName'] ?? $row['scientificName'] ?? ''));
                        $full = trim((string) ($row['scientificName'] ?? $canonical));
                        $authorship = null;
                        if ($canonical !== '' && str_starts_with($full, $canonical) && strlen($full) > strlen($canonical)) {
                            $authorship = trim(substr($full, strlen($canonical)));
                        }

                        $parts = preg_split('/\s+/', $canonical) ?: [];
                        $rank = strtolower((string) ($row['rank'] ?? 'species'));

                        $specificEpithet = in_array($rank, ['species', 'subspecies', 'variety', 'form'], true)
                            ? ($parts[1] ?? null)
                            : null;
                        $infraEpithet = in_array($rank, ['subspecies', 'variety', 'form'], true)
                            ? ($parts[2] ?? null)
                            : null;
                        $infraRank = match ($rank) {
                            'subspecies' => 'subsp.',
                            'variety' => 'var.',
                            'form' => 'f.',
                            default => null,
                        };

                        return [
                            'external_source' => 'gbif',
                            'external_taxon_id' => (string) ($row['key'] ?? $row['nubKey'] ?? ''),
                            'scientific_name' => $canonical,
                            'scientific_name_authorship' => $authorship ?: null,
                            'taxon_rank' => $this->normalizeRank($rank),
                            'kingdom' => $row['kingdom'] ?? null,
                            'phylum' => $row['phylum'] ?? null,
                            'taxon_class' => $row['class'] ?? null,
                            'taxon_order' => $row['order'] ?? null,
                            'family' => $row['family'] ?? null,
                            'genus' => $row['genus'] ?? ($parts[0] ?? null),
                            'specific_epithet' => $specificEpithet,
                            'infraspecific_rank' => $infraRank,
                            'infraspecific_epithet' => $infraEpithet,
                            'common_names' => [],
                        ];
                    })
                    ->unique(fn ($item) => strtolower($item['scientific_name']))
                    ->values()
                    ->all();
            } catch (Throwable) {
                return [];
            }
        });
    }

    private function normalizeRank(string $rank): string
    {
        return match ($rank) {
            'kingdom', 'phylum', 'class', 'order', 'family', 'genus', 'species', 'subspecies', 'variety', 'form', 'hybrid' => $rank,
            default => 'unranked',
        };
    }
}
