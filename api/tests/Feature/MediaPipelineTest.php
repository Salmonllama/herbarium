<?php

namespace Tests\Feature;

use App\Domain\Media\ExifExtractor;
use App\Enums\GeoprivacyLevel;
use App\Enums\HerbariumRole;
use App\Enums\SpecimenVisibility;
use App\Models\Herbarium;
use App\Models\HerbariumMembership;
use App\Models\Media;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_exif_dms_conversion_works_for_north_south_east_west(): void
    {
        $extractor = new ExifExtractor;
        $lat = $extractor->dmsToDecimal(['45/1', '30/1', '36/1'], 'N');
        $lng = $extractor->dmsToDecimal(['122/1', '40/1', '12/1'], 'W');

        $this->assertEquals(45.51, $lat);
        $this->assertEquals(-122.67, $lng);
    }

    public function test_media_upload_generates_exif_stripped_webp_derivatives_and_guards_originals(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $herbarium = Herbarium::create([
            'slug' => 'media-herb',
            'name' => 'Media Test Herbarium',
            'visibility' => 'public',
            'created_by' => $owner->id,
        ]);
        HerbariumMembership::create([
            'herbarium_id' => $herbarium->id,
            'user_id' => $owner->id,
            'role' => HerbariumRole::Owner,
            'can_view_exact_coordinates' => true,
        ]);

        $specimen = Specimen::create([
            'herbarium_id' => $herbarium->id,
            'catalog_number' => 'MED-0001',
            'visibility' => SpecimenVisibility::Published,
            'geoprivacy' => GeoprivacyLevel::Obscured,
            'decimal_latitude' => 45.5,
            'decimal_longitude' => -122.6,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner);
        $upload = UploadedFile::fake()->image('pressed-sheet.jpg', 1200, 1600);

        $response = $this->post("/api/v1/herbaria/media-herb/specimens/MED-0001/media", [
            'file' => $upload,
            'category' => 'sheet_scan',
            'caption' => 'Full herbarium sheet scan',
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.category', 'sheet_scan')
            ->assertJsonPath('data.processing_status', 'ready')
            ->assertJsonPath('data.width', 1200)
            ->assertJsonPath('data.height', 1600);

        $mediaId = $response->json('data.id');
        $media = Media::query()->findOrFail($mediaId);

        Storage::disk('local')->assertExists($media->original_path);
        Storage::disk('local')->assertExists($media->public_webp_path);
        Storage::disk('local')->assertExists($media->thumb_webp_path);

        // Public visitor can fetch EXIF-stripped WebP thumb and public view, but NOT raw original (specimen is obscured!)
        auth()->forgetGuards();
        $this->get("/api/v1/media/{$mediaId}/file/thumb")->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get("/api/v1/media/{$mediaId}/file/public")->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get("/api/v1/media/{$mediaId}/file/original")->assertForbidden();

        // Owner CAN fetch original
        Sanctum::actingAs($owner);
        $this->get("/api/v1/media/{$mediaId}/file/original")->assertOk();
    }
}
