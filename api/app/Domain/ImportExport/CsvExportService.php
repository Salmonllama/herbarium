<?php

namespace App\Domain\ImportExport;

use App\Domain\Geoprivacy\GeoprivacyService;
use App\Models\Herbarium;
use App\Models\Specimen;
use Illuminate\Support\Collection;

class CsvExportService
{
    public function __construct(
        private readonly GeoprivacyService $geoprivacy,
    ) {}

    /**
     * @param  Collection<int, Specimen>  $specimens
     */
    public function generate(Herbarium $herbarium, Collection $specimens, bool $canViewExact): string
    {
        $headers = [
            'catalogNumber',
            'recordNumber',
            'scientificName',
            'scientificNameAuthorship',
            'family',
            'genus',
            'taxonRank',
            'identifiedBy',
            'dateIdentified',
            'identificationQualifier',
            'recordedBy',
            'eventDate',
            'verbatimEventDate',
            'habitat',
            'substrate',
            'reproductiveCondition',
            'preparations',
            'typeStatus',
            'country',
            'countryCode',
            'stateProvince',
            'county',
            'municipality',
            'locality',
            'minimumElevationInMeters',
            'maximumElevationInMeters',
            'decimalLatitude',
            'decimalLongitude',
            'coordinateUncertaintyInMeters',
            'geodeticDatum',
            'informationWithheld',
            'dataGeneralizations',
            'visibility',
            'curationStatus',
        ];

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($specimens as $specimen) {
            $loc = $this->geoprivacy->presentLocation($specimen, $canViewExact);
            $det = $specimen->currentDetermination;
            $taxon = $det?->taxon;

            fputcsv($stream, [
                $specimen->catalog_number,
                $specimen->field_number,
                $taxon?->scientific_name,
                $taxon?->scientific_name_authorship,
                $taxon?->family,
                $taxon?->genus,
                $taxon?->taxon_rank?->value,
                $det?->identified_by,
                $det?->date_identified?->format('Y-m-d'),
                $det?->identification_qualifier,
                $specimen->recorded_by,
                $specimen->event_date?->format('Y-m-d'),
                $specimen->verbatim_event_date,
                $specimen->habitat,
                $specimen->substrate,
                $specimen->reproductive_condition,
                $specimen->preparations,
                $specimen->type_status,
                $specimen->country,
                $specimen->country_code,
                $specimen->state_province,
                $specimen->county,
                $specimen->municipality,
                $loc['locality'],
                $specimen->elevation_min_m,
                $specimen->elevation_max_m,
                $loc['decimal_latitude'],
                $loc['decimal_longitude'],
                $loc['coordinate_uncertainty_m'],
                $specimen->geodetic_datum,
                $loc['information_withheld'],
                $loc['data_generalizations'],
                $specimen->visibility->value,
                $specimen->curation_status->value,
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }
}
