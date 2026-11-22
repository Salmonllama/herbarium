<?php

namespace App\Domain\Labels;

use App\Domain\Geoprivacy\GeoprivacyService;
use App\Models\Herbarium;
use App\Models\Specimen;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Collection;

class LabelPdfRenderer
{
    public function __construct(
        private readonly GeoprivacyService $geoprivacy,
    ) {}

    /**
     * @param  Collection<int, Specimen>  $specimens
     */
    public function render(
        Herbarium $herbarium,
        Collection $specimens,
        string $template = 'letter_4up',
        bool $allowExactCoordinates = false,
    ): string {
        $qrOptions = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => 4,
            'outputBase64' => true,
            'quietzoneSize' => 1,
        ]);
        $qr = new QRCode($qrOptions);

        $baseUrl = rtrim((string) config('app.url'), '/');

        $labels = $specimens->map(function (Specimen $specimen) use ($herbarium, $allowExactCoordinates, $qr, $baseUrl) {
            $loc = $this->geoprivacy->presentLocation($specimen, $allowExactCoordinates);
            $taxon = $specimen->currentDetermination?->taxon;
            $det = $specimen->currentDetermination;

            $permalink = "{$baseUrl}/h/{$herbarium->slug}/specimens/".rawurlencode($specimen->catalog_number);
            $qrDataUri = $qr->render($permalink);

            $geoParts = array_filter([
                $specimen->country,
                $specimen->state_province,
                $specimen->county ? "{$specimen->county} Co." : null,
                $specimen->municipality,
            ]);

            $elev = null;
            if ($specimen->elevation_min_m !== null && $specimen->elevation_max_m !== null && $specimen->elevation_min_m !== $specimen->elevation_max_m) {
                $elev = "{$specimen->elevation_min_m}–{$specimen->elevation_max_m} m";
            } elseif ($specimen->elevation_min_m !== null) {
                $elev = "{$specimen->elevation_min_m} m";
            }

            $coords = null;
            if ($loc['decimal_latitude'] !== null && $loc['decimal_longitude'] !== null) {
                $coords = sprintf(
                    '%.5f, %.5f%s',
                    $loc['decimal_latitude'],
                    $loc['decimal_longitude'],
                    $loc['coordinates_obscured'] ? ' (obscured)' : '',
                );
            }

            return [
                'herbarium_name' => $herbarium->name,
                'institution_code' => $herbarium->institution_code,
                'catalog_number' => $specimen->catalog_number,
                'field_number' => $specimen->field_number,
                'family' => $taxon?->family ? strtoupper($taxon->family) : 'INDETERMINATAE',
                'scientific_name' => $taxon?->scientific_name ?? 'Indeterminata',
                'authorship' => $taxon?->scientific_name_authorship,
                'qualifier' => $det?->identification_qualifier,
                'type_status' => $specimen->type_status,
                'geo_header' => implode(', ', $geoParts),
                'locality' => $loc['locality'] ?? ($loc['information_withheld'] ? '[Locality withheld]' : null),
                'coordinates' => $coords,
                'elevation' => $elev,
                'habitat' => $specimen->habitat,
                'recorded_by' => $specimen->recorded_by,
                'event_date' => $specimen->event_date?->format('j M Y') ?? $specimen->verbatim_event_date,
                'identified_by' => $det?->identified_by,
                'date_identified' => $det?->date_identified?->format('Y'),
                'qr_data_uri' => $qrDataUri,
            ];
        })->all();

        $paper = match ($template) {
            'a4_4up' => 'a4',
            'single_4x3' => [0, 0, 288, 216], // 4 in x 3 in in points (72pt/in)
            default => 'letter',
        };

        $pdf = Pdf::loadView('pdf.herbarium-labels', [
            'herbarium' => $herbarium,
            'labels' => $labels,
            'template' => $template,
        ])->setPaper($paper, 'portrait');

        return $pdf->output();
    }
}
