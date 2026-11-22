<?php

namespace Tests\Feature;

use App\Enums\GeoprivacyLevel;
use App\Enums\HerbariumRole;
use App\Enums\SpecimenVisibility;
use App\Models\Herbarium;
use App\Models\HerbariumMembership;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use SimpleXMLElement;
use Tests\TestCase;
use ZipArchive;

class LabelsImportExportAndSsoTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_preview_import_export_dwca_zip_and_pdf_labels(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create(['name' => 'Dr. Curator']);
        $herbarium = Herbarium::create([
            'slug' => 'pnw-test',
            'name' => 'PNW Test Herbarium',
            'institution_code' => 'PNWT',
            'visibility' => 'public',
            'id_prefix' => 'PNWT',
            'id_padding' => 4,
            'created_by' => $owner->id,
        ]);
        HerbariumMembership::create([
            'herbarium_id' => $herbarium->id,
            'user_id' => $owner->id,
            'role' => HerbariumRole::Owner,
            'can_view_exact_coordinates' => true,
        ]);

        Sanctum::actingAs($owner);

        $csv = <<<CSV
CatalogNumber,Species,Family,Collector,Date,Country,State,Locality,Lat,Lng,Geoprivacy
,Quercus garryana,Fagaceae,E. Vance,2026-05-10,United States,Oregon,Tom McCall Preserve,45.6821,-121.3004,exact
PNWT-LEG-9,Cypripedium montanum,Orchidaceae,E. Vance,2026-06-01,United States,Oregon,Secret Ridge,45.5128,-121.5419,obscured
CSV;

        $file = UploadedFile::fake()->createWithContent('specimens.csv', $csv);

        // 1. Preview & auto-mapping
        $preview = $this->post('/api/v1/herbaria/pnw-test/imports/csv/preview', [
            'file' => $file,
        ], ['Accept' => 'application/json'])->assertOk();

        $mapping = $preview->json('suggested_mapping');
        $this->assertEquals('Species', $mapping['scientific_name']);
        $this->assertEquals('Lat', $mapping['decimal_latitude']);

        // 2. Execute live import (published)
        $importFile = UploadedFile::fake()->createWithContent('specimens.csv', $csv);
        $this->post('/api/v1/herbaria/pnw-test/imports/csv', [
            'file' => $importFile,
            'mapping' => json_encode($mapping),
            'default_visibility' => 'published',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('summary.created', 2)
            ->assertJsonPath('summary.taxa_created', 2)
            ->assertJsonPath('summary.skipped', 0);

        $this->assertDatabaseHas('specimens', ['catalog_number' => 'PNWT-0001']);
        $this->assertDatabaseHas('specimens', ['catalog_number' => 'PNWT-LEG-9']);

        // 3. Printable QR PDF Labels
        $pdfRes = $this->postJson('/api/v1/herbaria/pnw-test/labels/pdf', [
            'catalog_numbers' => ['PNWT-0001', 'PNWT-LEG-9'],
            'template' => 'letter_4up',
        ])->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $pdfRes->getContent());

        // 4. Public Darwin Core Archive (DwC-A) ZIP export enforces geoprivacy redaction
        auth()->forgetGuards();
        $dwcaRes = $this->get('/api/v1/herbaria/pnw-test/exports/dwca')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');

        $tmpZip = tempnam(sys_get_temp_dir(), 'test_dwca_');
        file_put_contents($tmpZip, $dwcaRes->getContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmpZip) === true);
        $metaXml = $zip->getFromName('meta.xml');
        $emlXml = $zip->getFromName('eml.xml');
        $occurrenceTxt = $zip->getFromName('occurrence.txt');
        $multimediaTxt = $zip->getFromName('multimedia.txt');
        $zip->close();
        @unlink($tmpZip);

        $this->assertNotFalse($metaXml);
        $this->assertNotFalse($emlXml);
        $this->assertNotFalse($occurrenceTxt);
        $this->assertNotFalse($multimediaTxt);

        // Validate XML structure
        $this->assertInstanceOf(SimpleXMLElement::class, new SimpleXMLElement($metaXml));
        $this->assertInstanceOf(SimpleXMLElement::class, new SimpleXMLElement($emlXml));

        // Verify geoprivacy redaction inside public DwC-A occurrence.txt
        $this->assertStringContainsString('Quercus garryana', $occurrenceTxt);
        $this->assertStringContainsString('Tom McCall Preserve', $occurrenceTxt);
        $this->assertStringNotContainsString('Secret Ridge', $occurrenceTxt);
        $this->assertStringContainsString('Coordinates generalized to a 0.1 degree', $occurrenceTxt);

        // 5. Public CSV export
        $csvRes = $this->get('/api/v1/herbaria/pnw-test/exports/csv')->assertOk();
        $this->assertStringContainsString('Quercus garryana', $csvRes->getContent());
        $this->assertStringNotContainsString('Secret Ridge', $csvRes->getContent());
    }

    public function test_orcid_oauth_flow_creates_and_authenticates_researcher(): void
    {
        config()->set('herbarium.auth_providers.orcid', [
            'enabled' => true,
            'label' => 'ORCID',
            'client_id' => 'APP-TEST-ID',
            'client_secret' => 'APP-TEST-SECRET',
            'redirect' => 'http://localhost/auth/orcid/callback',
            'sandbox' => true,
        ]);

        Http::fake([
            'sandbox.orcid.org/oauth/token' => Http::response([
                'access_token' => 'tok-123',
                'orcid' => '0000-0002-1825-0097',
                'name' => 'Dr. Josiah Carberry',
            ]),
        ]);

        $redirect = $this->get('/auth/orcid/redirect')->assertRedirect();
        parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $params);
        $this->assertArrayHasKey('state', $params);

        $this->get('/auth/orcid/callback?code=auth-code-1&state='.$params['state'])
            ->assertRedirect('/app');

        $this->assertDatabaseHas('users', [
            'orcid_id' => '0000-0002-1825-0097',
            'name' => 'Dr. Josiah Carberry',
        ]);
        $this->assertDatabaseHas('oauth_identities', [
            'provider' => 'orcid',
            'provider_user_id' => '0000-0002-1825-0097',
        ]);
    }

    public function test_bootstrap_artisan_command_is_idempotent_and_seeds_demo(): void
    {
        $this->artisan('herbarium:bootstrap', [
            '--email' => 'director@univ.edu',
            '--name' => 'Herbarium Director',
            '--password' => 'StrongPass123!',
            '--herbarium' => 'University Flora',
            '--prefix' => 'UFL',
            '--seed-demo' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'director@univ.edu', 'is_instance_admin' => true]);
        $this->assertDatabaseHas('herbaria', ['slug' => 'university-flora', 'id_prefix' => 'UFL']);
        $this->assertEquals(6, Specimen::query()->count());

        // Second invocation is idempotent
        $this->artisan('herbarium:bootstrap', [
            '--email' => 'director@univ.edu',
            '--herbarium' => 'University Flora',
            '--prefix' => 'UFL',
            '--seed-demo' => true,
        ])->assertSuccessful();

        $this->assertEquals(6, Specimen::query()->count());
    }
}
