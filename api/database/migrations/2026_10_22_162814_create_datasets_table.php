<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('datasets', function (Blueprint $table) {
        $table->uuid('id')->primary();

        $table->string('title')->nullable();
        $table->string('original_filename');
        $table->string('format');
        $table->string('status')->default('draft');

        $table->text('original_path')->nullable();
        $table->text('processed_path')->nullable();

        $table->string('postgis_schema')->nullable();
        $table->string('postgis_table')->nullable();

        $table->string('crs')->nullable();
        $table->integer('srid')->nullable();
        $table->string('geometry_type')->nullable();
        $table->bigInteger('feature_count')->nullable();

        $table->jsonb('bbox')->nullable();
        $table->jsonb('attribute_schema')->nullable();

        $table->integer('raster_width')->nullable();
        $table->integer('raster_height')->nullable();
        $table->integer('band_count')->nullable();

        $table->double('pixel_size_x')->nullable();
        $table->double('pixel_size_y')->nullable();

        $table->jsonb('nodata')->nullable();
        $table->jsonb('metadata')->nullable();

        $table->text('error_message')->nullable();

        $table->timestamps();

        $table->index('status');
        $table->index('format');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datasets');
    }
};
