<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('herbaria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 100)->unique();
            $table->string('name', 200);
            $table->string('institution_code', 32)->nullable()->index();
            $table->string('custom_domain', 255)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('website_url')->nullable();
            $table->string('visibility', 20)->default('public')->index(); // public, unlisted, private
            $table->string('id_prefix', 24)->default('HERB');
            $table->string('id_separator', 4)->default('-');
            $table->unsignedTinyInteger('id_padding')->default(6);
            $table->unsignedBigInteger('id_next_sequence')->default(1);
            $table->string('default_geoprivacy', 20)->default('exact'); // exact, obscured, hidden
            $table->string('default_license', 64)->default('CC-BY-4.0');
            $table->jsonb('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('herbarium_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('herbarium_id')->constrained('herbaria')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 24)->default('viewer'); // owner, curator, contributor, viewer
            $table->boolean('can_view_exact_coordinates')->default(false);
            $table->timestamps();

            $table->unique(['herbarium_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('herbarium_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('herbarium_id')->constrained('herbaria')->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 24)->default('contributor');
            $table->boolean('can_view_exact_coordinates')->default(false);
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['herbarium_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('herbarium_invitations');
        Schema::dropIfExists('herbarium_memberships');
        Schema::dropIfExists('herbaria');
    }
};
