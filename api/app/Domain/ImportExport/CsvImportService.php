<?php

namespace App\Domain\ImportExport;

use App\Domain\Specimens\CatalogNumberGenerator;
use App\Domain\Specimens\DeterminationService;
use App\Enums\CurationStatus;
use App\Enums\GeoprivacyLevel;
use App\Enums\SpecimenVisibility;
use App\Models\Herbarium;
use App\Models\Specimen;
use App\Models\Taxon;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class CsvImportService
{
    /**
     * Canonical target fields supported by the CSV import mapping wizard.
     *
     * @var array<string, array<int, string>>
     */
    public const FIELD_ALIASES = [
        'catalog_number' => ['catalog_number', 'catalognumber', 'catalog_id', 'accession', 'accession_number', 'id', 'barcode'],
        'field_number' => ['field_number', 'recordnumber', 'collector_number', 'collection_number'],
        'scientific_name' => ['scientific_name', 'scientificname', 'species', 'taxon', 'plant_name', 'binomial'],
        'scientific_name_authorship' => ['scientific_name_authorship', 'scientificnameauthorship', 'author', 'authorship'],
        'family' => ['family'],
        'genus' => ['genus'],
        'recorded_by' => ['recorded_by', 'recordedby', 'collector', 'collectors', 'leg'],
        'event_date' => ['event_date', 'eventdate', 'date', 'collection_date', 'collected_at'],
        'verbatim_event_date' => ['verbatim_event_date', 'verbatimeventdate'],
        'country' => ['country'],
        'country_code' => ['country_code', 'countrycode'],
        'state_province' => ['state_province', 'stateprovince', 'state', 'province', 'region'],
        'county' => ['county'],
        'municipality' => ['municipality', 'city'],
        'locality' => ['locality', 'location', 'site'],
        'habitat' => ['habitat', 'ecology'],
        'substrate' => ['substrate'],
        'reproductive_condition' => ['reproductive_condition', 'reproductivecondition', 'phenology'],
        'preparations' => ['preparations', 'preparation'],
        'type_status' => ['type_status', 'typestatus'],
        'elevation_min_m' => ['elevation_min_m', 'minimumelevationinmeters', 'elevation', 'elevation_m'],
        'elevation_max_m' => ['elevation_max_m', 'maximumelevationinmeters'],
        'decimal_latitude' => ['decimal_latitude', 'decimallatitude', 'latitude', 'lat'],
        'decimal_longitude' => ['decimal_longitude', 'decimallongitude', 'longitude', 'lng', 'lon'],
        'coordinate_uncertainty_m' => ['coordinate_uncertainty_m', 'coordinateuncertaintyinmeters', 'gps_accuracy'],
        'geoprivacy' => ['geoprivacy'],
        'visibility' => ['visibility'],
        'curation_status' => ['curation_status', 'status'],
        'storage_room' => ['storage_room', 'room'],
        'storage_cabinet' => ['storage_cabinet', 'cabinet'],
        'storage_shelf' => ['storage_shelf', 'shelf'],
        'storage_folder' => ['storage_folder', 'folder'],
        'occurrence_remarks' => ['occurrence_remarks', 'occurrenceremarks', 'notes', 'remarks'],
    ];

    public function __construct(
        private readonly CatalogNumberGenerator $catalogGenerator,
        private readonly DeterminationService $determinations,
    ) {}

    /**
     * Parse CSV headers, sample rows, and auto-suggested column mapping.
     *
     * @return array{headers: array<int, string>, total_rows: int, sample_rows: array<int, array<string, string>>, suggested_mapping: array<string, string>, supported_fields: array<int, string>}
     */
    public function preview(string $csvContent): array
    {
        [$headers, $rows] = $this->parseCsv($csvContent);
        $suggested = [];

        foreach ($headers as $header) {
            $norm = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($header)) ?? '');
            $norm = trim($norm, '_');
            $compact = str_replace('_', '', $norm);

            foreach (self::FIELD_ALIASES as $target => $aliases) {
                if (in_array($norm, $aliases, true) || in_array($compact, $aliases, true)) {
                    if (! isset($suggested[$target])) {
                        $suggested[$target] = $header;
                    }
                    break;
                }
            }
        }

        return [
            'headers' => $headers,
            'total_rows' => count($rows),
            'sample_rows' => array_slice($rows, 0, 5),
            'suggested_mapping' => $suggested,
            'supported_fields' => array_keys(self::FIELD_ALIASES),
        ];
    }

    /**
     * Execute (or dry-run) a mapped CSV import into a Herbarium.
     *
     * @param  array<string, string>  $mapping  targetField => csvHeader
     * @return array{dry_run: bool, total_rows: int, created: int, taxa_created: int, skipped: int, errors: array<int, array{row: int, message: string}>}
     */
    public function import(
        Herbarium $herbarium,
        User $actor,
        string $csvContent,
        array $mapping,
        bool $dryRun = false,
        string $defaultVisibility = 'draft',
    ): array {
        [$headers, $rows] = $this->parseCsv($csvContent);
        $created = 0;
        $taxaCreated = 0;
        $skipped = 0;
        $errors = [];

        $allowedVisibility = in_array($defaultVisibility, ['draft', 'private', 'published'], true)
            ? $defaultVisibility
            : SpecimenVisibility::Draft->value;

        foreach ($rows as $idx => $row) {
            $rowNumber = $idx + 2; // 1-indexed including header row
            $mapped = [];
            foreach ($mapping as $targetField => $csvHeader) {
                if (! array_key_exists($targetField, self::FIELD_ALIASES) || $csvHeader === '') {
                    continue;
                }
                $val = isset($row[$csvHeader]) ? trim((string) $row[$csvHeader]) : '';
                $mapped[$targetField] = $val !== '' ? $val : null;
            }

            // Validate coordinates if provided
            if (isset($mapped['decimal_latitude']) && (! is_numeric($mapped['decimal_latitude']) || (float) $mapped['decimal_latitude'] < -90 || (float) $mapped['decimal_latitude'] > 90)) {
                $errors[] = ['row' => $rowNumber, 'message' => "Invalid latitude '{$mapped['decimal_latitude']}'."];
                $skipped++;

                continue;
            }
            if (isset($mapped['decimal_longitude']) && (! is_numeric($mapped['decimal_longitude']) || (float) $mapped['decimal_longitude'] < -180 || (float) $mapped['decimal_longitude'] > 180)) {
                $errors[] = ['row' => $rowNumber, 'message' => "Invalid longitude '{$mapped['decimal_longitude']}'."];
                $skipped++;

                continue;
            }

            if (! empty($mapped['catalog_number'])) {
                $exists = Specimen::query()
                    ->where('herbarium_id', $herbarium->id)
                    ->where('catalog_number', $mapped['catalog_number'])
                    ->exists();
                if ($exists) {
                    $errors[] = ['row' => $rowNumber, 'message' => "Duplicate catalog_number '{$mapped['catalog_number']}'."];
                    $skipped++;

                    continue;
                }
            }

            if ($dryRun) {
                $created++;

                continue;
            }

            try {
                DB::transaction(function () use ($herbarium, $actor, $mapped, $allowedVisibility, &$created, &$taxaCreated) {
                    $taxon = null;
                    if (! empty($mapped['scientific_name'])) {
                        $sciName = $mapped['scientific_name'];
                        $taxon = Taxon::query()
                            ->where('herbarium_id', $herbarium->id)
                            ->whereRaw('LOWER(scientific_name) = ?', [strtolower($sciName)])
                            ->first();

                        if (! $taxon) {
                            $parts = preg_split('/\s+/', $sciName) ?: [];
                            $taxon = Taxon::create([
                                'herbarium_id' => $herbarium->id,
                                'scientific_name' => $sciName,
                                'scientific_name_authorship' => $mapped['scientific_name_authorship'] ?? null,
                                'family' => $mapped['family'] ?? null,
                                'genus' => $mapped['genus'] ?? ($parts[0] ?? null),
                                'specific_epithet' => $parts[1] ?? null,
                                'external_source' => 'manual',
                                'created_by' => $actor->id,
                            ]);
                            $taxaCreated++;
                        }
                    }

                    $catalogNumber = ! empty($mapped['catalog_number'])
                        ? $mapped['catalog_number']
                        : $this->catalogGenerator->nextFor($herbarium);

                    $visibility = SpecimenVisibility::tryFrom((string) ($mapped['visibility'] ?? ''))?->value ?? $allowedVisibility;
                    $status = CurationStatus::tryFrom((string) ($mapped['curation_status'] ?? ''))?->value ?? CurationStatus::Unmounted->value;
                    $geo = GeoprivacyLevel::tryFrom((string) ($mapped['geoprivacy'] ?? ''))?->value ?? GeoprivacyLevel::Inherit->value;

                    $specimen = Specimen::create([
                        'herbarium_id' => $herbarium->id,
                        'catalog_number' => $catalogNumber,
                        'field_number' => $mapped['field_number'] ?? null,
                        'visibility' => $visibility,
                        'curation_status' => $status,
                        'recorded_by' => $mapped['recorded_by'] ?? null,
                        'event_date' => $this->parseDateOrNull($mapped['event_date'] ?? null),
                        'verbatim_event_date' => $mapped['verbatim_event_date'] ?? ($this->parseDateOrNull($mapped['event_date'] ?? null) === null ? ($mapped['event_date'] ?? null) : null),
                        'country' => $mapped['country'] ?? null,
                        'country_code' => isset($mapped['country_code']) ? strtoupper(substr($mapped['country_code'], 0, 2)) : null,
                        'state_province' => $mapped['state_province'] ?? null,
                        'county' => $mapped['county'] ?? null,
                        'municipality' => $mapped['municipality'] ?? null,
                        'locality' => $mapped['locality'] ?? null,
                        'habitat' => $mapped['habitat'] ?? null,
                        'substrate' => $mapped['substrate'] ?? null,
                        'reproductive_condition' => $mapped['reproductive_condition'] ?? null,
                        'preparations' => $mapped['preparations'] ?? 'pressed sheet',
                        'type_status' => $mapped['type_status'] ?? null,
                        'elevation_min_m' => isset($mapped['elevation_min_m']) && is_numeric($mapped['elevation_min_m']) ? (int) $mapped['elevation_min_m'] : null,
                        'elevation_max_m' => isset($mapped['elevation_max_m']) && is_numeric($mapped['elevation_max_m']) ? (int) $mapped['elevation_max_m'] : null,
                        'decimal_latitude' => isset($mapped['decimal_latitude']) ? (float) $mapped['decimal_latitude'] : null,
                        'decimal_longitude' => isset($mapped['decimal_longitude']) ? (float) $mapped['decimal_longitude'] : null,
                        'coordinate_uncertainty_m' => isset($mapped['coordinate_uncertainty_m']) && is_numeric($mapped['coordinate_uncertainty_m']) ? (int) $mapped['coordinate_uncertainty_m'] : null,
                        'geoprivacy' => $geo,
                        'storage_room' => $mapped['storage_room'] ?? null,
                        'storage_cabinet' => $mapped['storage_cabinet'] ?? null,
                        'storage_shelf' => $mapped['storage_shelf'] ?? null,
                        'storage_folder' => $mapped['storage_folder'] ?? null,
                        'occurrence_remarks' => $mapped['occurrence_remarks'] ?? null,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);

                    if ($taxon) {
                        $this->determinations->record($specimen, [
                            'taxon_id' => $taxon->id,
                            'identified_by' => $actor->name,
                        ], $actor);
                    }

                    $created++;
                });
            } catch (Throwable $e) {
                $errors[] = ['row' => $rowNumber, 'message' => $e->getMessage()];
                $skipped++;
            }
        }

        return [
            'dry_run' => $dryRun,
            'total_rows' => count($rows),
            'created' => $created,
            'taxa_created' => $taxaCreated,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 100),
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<string, string>>}
     */
    private function parseCsv(string $csvContent): array
    {
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent) ?? $csvContent;
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $clean);
        rewind($stream);

        $headers = fgetcsv($stream);
        if (! is_array($headers)) {
            fclose($stream);

            return [[], []];
        }

        $headers = array_map(fn ($h) => trim((string) $h), $headers);
        $rows = [];

        while (($line = fgetcsv($stream)) !== false) {
            if (count($line) === 1 && trim((string) $line[0]) === '') {
                continue;
            }
            $assoc = [];
            foreach ($headers as $i => $header) {
                $assoc[$header] = isset($line[$i]) ? (string) $line[$i] : '';
            }
            $rows[] = $assoc;
        }

        fclose($stream);

        return [$headers, $rows];
    }

    private function parseDateOrNull(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        $ts = strtotime($value);

        return $ts !== false ? date('Y-m-d', $ts) : null;
    }
}
