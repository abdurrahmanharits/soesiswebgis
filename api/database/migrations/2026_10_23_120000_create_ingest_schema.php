<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Schema that ProcessSpatialDataset imports Shapefiles into (ogr2ogr -nln ingest.ds_*).
     */
    public function up(): void
    {
        DB::statement('create schema if not exists ingest');
    }

    /**
     * Without CASCADE this refuses to drop the schema while imported tables exist.
     */
    public function down(): void
    {
        DB::statement('drop schema if exists ingest');
    }
};
