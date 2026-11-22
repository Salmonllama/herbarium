<?php

namespace App\Domain\Taxonomy;

use App\Contracts\TaxonLookupProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class KewPowoProvider implements TaxonLookupProvider
{
    public function name(): string
    {
        return 'powo';
    }

    public function search(string $query, int $limit = 10): array
    {
        $normalized = strtolower(trim($query));
        if ($normalized === '') {
            return [];
        }

        $cacheKey = 'herbarium:taxonomy:powo:'.md5($normalized.'|'.$limit);
        $ttl = (int) config('herbarium.taxonomy.cache_ttl_seconds', 86400);

        return Cache::remember($cacheKey, $ttl, function () use ($query, $limit) {
            try {
                $baseUrl = rtrim((string) config('herbarium.taxonomy.powo_base_url', 'https://powo.science.kew.org/api/2'), '/');
                $response = Http::timeout(8)
                    ->acceptJson()
                    ->get("{$baseUrl}/search", [
                        'q' => $query,
                        'perPage' => min(25, max(1, $limit)),
                    ]);

                if (! $response->successful()) {
                    return [];
                }

                $results = $response->json('results');
                if (! is_array($results)) {
                    return [];
                }

                return collect($results)
                    ->filter(fn ($row) => is_array($row) && ! empty($row['name']))
                    ->map(function (array $row) {
                        $name = trim((string) $row['name']);
                        $rank = strtolower((string) ($row['rank'] ?? 'species'));
                        $parts = preg_split('/\s+/', $name) ?: [];

                        return [
                            'external_source' => 'powo',
                            'external_taxon_id' => (string) ($row['fqId'] ?? $row['url'] ?? ''),
                            'scientific_name' => $name,
                            'scientific_name_authorship' => $row['author'] ?? null,
                            'taxon_rank' => match ($rank) {
                                'kingdom', 'phylum', 'class', 'order', 'family', 'genus', 'species', 'subspecies', 'variety', 'form', 'hybrid' => $rank,
                                default => 'species',
                            },
                            'kingdom' => 'Plantae',
                            'phylum' => null,
                            'taxon_class' => null,
                            'taxon_order' => null,
                            'family' => $row['family'] ?? null,
                            'genus' => $parts[0] ?? null,
                            'specific_epithet' => $parts[1] ?? null,
                            'infraspecific_rank' => null,
                            'infraspecific_epithet' => null,
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
}
