<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('herbarium_id')->constrained('herbaria')->cascadeOnDelete();
            $table->string('mediable_type', 120);
            $table->uuid('mediable_id');
            $table->string('category', 32)->default('sheet_scan'); // sheet_scan, field_photo, closeup, label, reference
            $table->string('disk', 32)->default('local');
            $table->string('original_path', 512);
            $table->string('public_webp_path', 512)->nullable();
            $table->string('thumb_webp_path', 512)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->jsonb('exif_extracted')->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('photographer', 255)->nullable();
            $table->string('license_code', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('processing_status', 24)->default('pending'); // pending, ready, failed
            $table->text('processing_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id', 'sort_order']);
            $table->index(['herbarium_id', 'processing_status']);
        });

        Schema::create('import_export_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('herbarium_id')->constrained('herbaria')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32); // csv_import, csv_export, dwca_export, labels_pdf
            $table->string('status', 24)->default('pending'); // pending, processing, completed, failed
            $table->string('disk', 32)->default('local');
            $table->string('file_path', 512)->nullable();
            $table->string('download_filename', 255)->nullable();
            $table->jsonb('options')->nullable();
            $table->jsonb('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['herbarium_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_export_jobs');
        Schema::dropIfExists('media');
    }
};
