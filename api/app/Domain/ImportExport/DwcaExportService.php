<?php

namespace App\Domain\ImportExport;

use App\Contracts\MediaUrlResolver;
use App\Domain\Geoprivacy\GeoprivacyService;
use App\Models\Herbarium;
use App\Models\Specimen;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class DwcaExportService
{
    private const OCCURRENCE_FIELDS = [
        'occurrenceID' => 'http://rs.tdwg.org/dwc/terms/occurrenceID',
        'basisOfRecord' => 'http://rs.tdwg.org/dwc/terms/basisOfRecord',
        'institutionCode' => 'http://rs.tdwg.org/dwc/terms/institutionCode',
        'collectionCode' => 'http://rs.tdwg.org/dwc/terms/collectionCode',
        'catalogNumber' => 'http://rs.tdwg.org/dwc/terms/catalogNumber',
        'recordNumber' => 'http://rs.tdwg.org/dwc/terms/recordNumber',
        'scientificName' => 'http://rs.tdwg.org/dwc/terms/scientificName',
        'scientificNameAuthorship' => 'http://rs.tdwg.org/dwc/terms/scientificNameAuthorship',
        'kingdom' => 'http://rs.tdwg.org/dwc/terms/kingdom',
        'phylum' => 'http://rs.tdwg.org/dwc/terms/phylum',
        'class' => 'http://rs.tdwg.org/dwc/terms/class',
        'order' => 'http://rs.tdwg.org/dwc/terms/order',
        'family' => 'http://rs.tdwg.org/dwc/terms/family',
        'genus' => 'http://rs.tdwg.org/dwc/terms/genus',
        'specificEpithet' => 'http://rs.tdwg.org/dwc/terms/specificEpithet',
        'infraspecificEpithet' => 'http://rs.tdwg.org/dwc/terms/infraspecificEpithet',
        'taxonRank' => 'http://rs.tdwg.org/dwc/terms/taxonRank',
        'identifiedBy' => 'http://rs.tdwg.org/dwc/terms/identifiedBy',
        'dateIdentified' => 'http://rs.tdwg.org/dwc/terms/dateIdentified',
        'identificationQualifier' => 'http://rs.tdwg.org/dwc/terms/identificationQualifier',
        'recordedBy' => 'http://rs.tdwg.org/dwc/terms/recordedBy',
        'eventDate' => 'http://rs.tdwg.org/dwc/terms/eventDate',
        'verbatimEventDate' => 'http://rs.tdwg.org/dwc/terms/verbatimEventDate',
        'habitat' => 'http://rs.tdwg.org/dwc/terms/habitat',
        'reproductiveCondition' => 'http://rs.tdwg.org/dwc/terms/reproductiveCondition',
        'preparations' => 'http://rs.tdwg.org/dwc/terms/preparations',
        'typeStatus' => 'http://rs.tdwg.org/dwc/terms/typeStatus',
        'occurrenceRemarks' => 'http://rs.tdwg.org/dwc/terms/occurrenceRemarks',
        'country' => 'http://rs.tdwg.org/dwc/terms/country',
        'countryCode' => 'http://rs.tdwg.org/dwc/terms/countryCode',
        'stateProvince' => 'http://rs.tdwg.org/dwc/terms/stateProvince',
        'county' => 'http://rs.tdwg.org/dwc/terms/county',
        'municipality' => 'http://rs.tdwg.org/dwc/terms/municipality',
        'locality' => 'http://rs.tdwg.org/dwc/terms/locality',
        'minimumElevationInMeters' => 'http://rs.tdwg.org/dwc/terms/minimumElevationInMeters',
        'maximumElevationInMeters' => 'http://rs.tdwg.org/dwc/terms/maximumElevationInMeters',
        'decimalLatitude' => 'http://rs.tdwg.org/dwc/terms/decimalLatitude',
        'decimalLongitude' => 'http://rs.tdwg.org/dwc/terms/decimalLongitude',
        'coordinateUncertaintyInMeters' => 'http://rs.tdwg.org/dwc/terms/coordinateUncertaintyInMeters',
        'geodeticDatum' => 'http://rs.tdwg.org/dwc/terms/geodeticDatum',
        'informationWithheld' => 'http://rs.tdwg.org/dwc/terms/informationWithheld',
        'dataGeneralizations' => 'http://rs.tdwg.org/dwc/terms/dataGeneralizations',
        'license' => 'http://purl.org/dc/terms/license',
    ];

    private const MULTIMEDIA_FIELDS = [
        'occurrenceID' => 'http://rs.tdwg.org/dwc/terms/occurrenceID',
        'type' => 'http://purl.org/dc/terms/type',
        'format' => 'http://purl.org/dc/terms/format',
        'identifier' => 'http://purl.org/dc/terms/identifier',
        'title' => 'http://purl.org/dc/terms/title',
        'creator' => 'http://purl.org/dc/terms/creator',
        'license' => 'http://purl.org/dc/terms/license',
    ];

    public function __construct(
        private readonly GeoprivacyService $geoprivacy,
        private readonly MediaUrlResolver $mediaUrls,
    ) {}

    /**
     * Generates a standards-compliant Darwin Core Archive (DwC-A) ZIP file
     * containing meta.xml, eml.xml, occurrence.txt, and multimedia.txt.
     *
     * @param  Collection<int, Specimen>  $specimens
     */
    public function buildArchiveBytes(Herbarium $herbarium, Collection $specimens, bool $canViewExact): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'dwca_');
        if ($tmpPath === false) {
            throw new RuntimeException('Unable to create temporary file for DwC-A archive.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpPath);
            throw new RuntimeException('Unable to open ZipArchive for DwC-A export.');
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $occurrenceLines = [implode("\t", array_keys(self::OCCURRENCE_FIELDS))];
        $multimediaLines = [implode("\t", array_keys(self::MULTIMEDIA_FIELDS))];

        foreach ($specimens as $specimen) {
            $occurrenceId = "{$baseUrl}/h/{$herbarium->slug}/specimens/".rawurlencode($specimen->catalog_number);
            $loc = $this->geoprivacy->presentLocation($specimen, $canViewExact);
            $det = $specimen->currentDetermination;
            $taxon = $det?->taxon;

            $row = [
                $occurrenceId,
                'PreservedSpecimen',
                $herbarium->institution_code ?? $herbarium->id_prefix,
                $herbarium->slug,
                $specimen->catalog_number,
                $specimen->field_number,
                $taxon?->scientific_name,
                $taxon?->scientific_name_authorship,
                $taxon?->kingdom ?? 'Plantae',
                $taxon?->phylum,
                $taxon?->taxon_class,
                $taxon?->taxon_order,
                $taxon?->family,
                $taxon?->genus,
                $taxon?->specific_epithet,
                $taxon?->infraspecific_epithet,
                $taxon?->taxon_rank?->value,
                $det?->identified_by,
                $det?->date_identified?->format('Y-m-d'),
                $det?->identification_qualifier,
                $specimen->recorded_by,
                $specimen->event_date?->format('Y-m-d'),
                $specimen->verbatim_event_date,
                $specimen->habitat,
                $specimen->reproductive_condition,
                $specimen->preparations,
                $specimen->type_status,
                $specimen->occurrence_remarks,
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
                $herbarium->default_license,
            ];

            $occurrenceLines[] = implode("\t", array_map([$this, 'sanitizeTsv'], $row));

            foreach ($specimen->media as $media) {
                $url = $this->mediaUrls->url($media, 'public');
                if (! $url) {
                    continue;
                }
                $fullUrl = str_starts_with($url, 'http') ? $url : "{$baseUrl}{$url}";
                $multimediaLines[] = implode("\t", array_map([$this, 'sanitizeTsv'], [
                    $occurrenceId,
                    'StillImage',
                    'image/webp',
                    $fullUrl,
                    $media->caption ?: "{$specimen->catalog_number} ({$media->category->value})",
                    $media->photographer,
                    $media->license_code ?: $herbarium->default_license,
                ]));
            }
        }

        $zip->addFromString('meta.xml', $this->buildMetaXml());
        $zip->addFromString('eml.xml', $this->buildEmlXml($herbarium, $baseUrl));
        $zip->addFromString('occurrence.txt', implode("\n", $occurrenceLines)."\n");
        $zip->addFromString('multimedia.txt', implode("\n", $multimediaLines)."\n");
        $zip->close();

        $bytes = file_get_contents($tmpPath) ?: '';
        @unlink($tmpPath);

        return $bytes;
    }

    private function buildMetaXml(): string
    {
        $coreFieldsXml = '';
        $i = 0;
        foreach (self::OCCURRENCE_FIELDS as $term) {
            if ($i > 0) {
                $coreFieldsXml .= "    <field index=\"{$i}\" term=\"{$term}\"/>\n";
            }
            $i++;
        }

        $extFieldsXml = '';
        $j = 0;
        foreach (self::MULTIMEDIA_FIELDS as $term) {
            if ($j > 0) {
                $extFieldsXml .= "    <field index=\"{$j}\" term=\"{$term}\"/>\n";
            }
            $j++;
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<archive xmlns="http://rs.tdwg.org/dwc/text/" metadata="eml.xml">
  <core encoding="UTF-8" fieldsTerminatedBy="\t" linesTerminatedBy="\n" fieldsEnclosedBy="" ignoreHeaderLines="1" rowType="http://rs.tdwg.org/dwc/terms/Occurrence">
    <files>
      <location>occurrence.txt</location>
    </files>
    <id index="0"/>
{$coreFieldsXml}  </core>
  <extension encoding="UTF-8" fieldsTerminatedBy="\t" linesTerminatedBy="\n" fieldsEnclosedBy="" ignoreHeaderLines="1" rowType="http://rs.gbif.org/terms/1.0/Multimedia">
    <files>
      <location>multimedia.txt</location>
    </files>
    <coreid index="0"/>
{$extFieldsXml}  </extension>
</archive>
XML;
    }

    private function buildEmlXml(Herbarium $herbarium, string $baseUrl): string
    {
        $title = htmlspecialchars($herbarium->name, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $abstract = htmlspecialchars($herbarium->description ?: "Digital herbarium specimen dataset published by {$herbarium->name}.", ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($herbarium->contact_email ?: 'curator@herbarium.local', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $license = htmlspecialchars($herbarium->default_license ?: 'CC-BY-4.0', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $datasetUrl = htmlspecialchars("{$baseUrl}/h/{$herbarium->slug}", ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $pubDate = now()->toDateString();

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eml:eml xmlns:eml="https://eml.ecoinformatics.org/eml-2.2.0" packageId="herbarium-{$herbarium->id}" system="herbarium">
  <dataset>
    <alternateIdentifier>{$datasetUrl}</alternateIdentifier>
    <title xml:lang="en">{$title}</title>
    <pubDate>{$pubDate}</pubDate>
    <language>en</language>
    <abstract>
      <para>{$abstract}</para>
    </abstract>
    <intellectualRights>
      <para>This dataset is licensed under {$license}.</para>
    </intellectualRights>
    <contact>
      <organizationName>{$title}</organizationName>
      <electronicMailAddress>{$email}</electronicMailAddress>
    </contact>
  </dataset>
</eml:eml>
XML;
    }

    private function sanitizeTsv(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim(str_replace(["\t", "\r", "\n"], ' ', (string) $value));
    }
}
