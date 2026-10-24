<?php

namespace Tests\Feature;

use App\Models\Dataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    private const TABLE = 'ds_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const COG_PATH = 'processed/catalogue/cog.tif';

    private function dataset(array $attributes = []): Dataset
    {
        return Dataset::create([
            'title' => 'Batas Desa',
            'original_filename' => 'desa.zip',
            'format' => 'shapefile',
            'status' => 'ready_for_curation',
            'curation_status' => 'approved',
            'crs' => 'EPSG:4326',
            'srid' => 4326,
            'bbox' => [96.0, 4.0, 96.1, 4.1],
            'description' => 'Batas administrasi desa.',
            'source' => 'Survei lapangan',
            'license' => 'CC-BY-4.0',
            ...$attributes,
        ]);
    }

    private function raster(array $attributes = []): Dataset
    {
        Storage::fake('local');
        Storage::disk('local')->put(self::COG_PATH, str_repeat('A', 4096));
        Process::fake(['*gdalinfo*' => Process::result(json_encode(['bands' => [['minimum' => 1, 'maximum' => 9]]]))]);

        return $this->dataset([
            'title' => 'DEM',
            'format' => 'geotiff',
            'bbox' => null,
            'processed_path' => self::COG_PATH,
            'band_count' => 1,
            'metadata' => [
                'wgs84Extent' => [
                    'type' => 'Polygon',
                    'coordinates' => [[[94.75, 6.30], [94.75, 1.78], [98.44, 1.78], [98.44, 6.30], [94.75, 6.30]]],
                ],
            ],
            ...$attributes,
        ]);
    }

    /** Every dataset state that must stay out of the public catalogue. */
    private function unpublished(): array
    {
        return [
            'pending' => $this->dataset(['title' => 'Pending', 'curation_status' => 'pending']),
            'rejected' => $this->dataset(['title' => 'Rejected', 'curation_status' => 'rejected']),
            'failed' => $this->dataset(['title' => 'Failed', 'status' => 'failed', 'curation_status' => 'pending']),
            'processing' => $this->dataset(['title' => 'Processing', 'status' => 'processing', 'curation_status' => 'pending']),
            // Defensive: approved but no longer ingested must not be public either.
            'approved but failed' => $this->dataset(['title' => 'Approved failed', 'status' => 'failed']),
        ];
    }

    private function createTable(): void
    {
        DB::statement('create schema if not exists ingest');
        DB::statement('create table ingest.'.self::TABLE.' (ogc_fid serial primary key, geom geometry(MultiPolygon, 4326), nama varchar)');
        DB::insert('insert into ingest.'.self::TABLE." (geom, nama) values (ST_Multi(ST_MakeEnvelope(96, 4, 96.05, 4.05, 4326)), 'Desa 1')");
    }

    // ---- GET /api/catalogue ---------------------------------------------

    public function test_lists_only_published_datasets(): void
    {
        $this->unpublished();
        $this->dataset(['title' => 'Published']);

        $this->getJson('/api/catalogue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Published');
    }

    public function test_empty_catalogue_returns_empty_list(): void
    {
        $this->unpublished();

        $this->getJson('/api/catalogue')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_lists_newest_first(): void
    {
        $old = $this->dataset(['title' => 'Old']);
        $old->forceFill(['created_at' => now()->subDay()])->save();
        $this->dataset(['title' => 'New']);

        $this->getJson('/api/catalogue')->assertJsonPath('data.*.title', ['New', 'Old']);
    }

    public function test_list_items_contain_only_public_fields(): void
    {
        $this->dataset();

        $item = $this->getJson('/api/catalogue')->json('data.0');

        $this->assertSame([
            'id', 'title', 'description', 'format', 'crs', 'extent', 'geometry_type', 'feature_count',
            'band_count', 'keywords', 'topic_category', 'source', 'source_organization', 'license',
            'license_other', 'temporal_start', 'temporal_end', 'geographic_description', 'created_at', 'published_at',
        ], array_keys($item));
    }

    public function test_public_responses_never_leak_internal_fields(): void
    {
        $dataset = $this->dataset([
            'original_path' => 'datasets/secret/original.zip',
            'processed_path' => 'processed/secret/cog.tif',
            'postgis_schema' => 'ingest',
            'postgis_table' => 'ds_secret_table',
            'attribute_schema' => [['name' => 'secret_column']],
            'metadata' => ['layers' => [['name' => 'raw ogrinfo secret']]],
            'error_message' => 'secret error',
            'contact_name' => 'Rina',
            'contact_email' => 'rina@example.org',
        ]);
        $dataset->curationEvents()->create([
            'action' => 'approved', 'reviewer_name' => 'Secret Reviewer', 'note' => 'secret note',
            'from_status' => 'pending', 'to_status' => 'approved',
        ]);

        foreach (['/api/catalogue', "/api/catalogue/{$dataset->id}"] as $url) {
            $body = $this->getJson($url)->assertOk()->getContent();

            foreach (['secret', 'ingest', 'rina@example.org', 'Secret Reviewer', 'attribute_schema', 'metadata', 'original_path', 'processed_path', 'postgis'] as $needle) {
                $this->assertStringNotContainsString($needle, $body, "{$url} leaks {$needle}");
            }
        }
    }

    public function test_extents_are_wgs84_for_vector_and_raster(): void
    {
        $this->dataset(['crs' => 'EPSG:32750', 'srid' => 32750, 'bbox' => [500000, 9900000, 501000, 9901000]]);
        $this->raster();

        $items = collect($this->getJson('/api/catalogue')->json('data'))->keyBy('format');

        $this->assertEqualsWithDelta(117.0, $items['shapefile']['extent'][0], 0.01);
        $this->assertSame([94.75, 1.78, 98.44, 6.30], $items['geotiff']['extent']);
    }

    public function test_published_at_is_latest_approval(): void
    {
        $dataset = $this->dataset();
        $dataset->curationEvents()->forceCreate(['action' => 'approved', 'reviewer_name' => 'QA', 'from_status' => 'pending', 'to_status' => 'approved', 'created_at' => '2025-03-01 10:00:00']);
        $dataset->curationEvents()->forceCreate(['action' => 'reopened', 'reviewer_name' => 'QA', 'from_status' => 'approved', 'to_status' => 'pending', 'created_at' => '2026-01-01 10:00:00']);
        $dataset->curationEvents()->forceCreate(['action' => 'approved', 'reviewer_name' => 'QA', 'from_status' => 'pending', 'to_status' => 'approved', 'created_at' => '2026-02-01 10:00:00']);

        $this->assertStringStartsWith('2026-02-01', $this->getJson('/api/catalogue')->json('data.0.published_at'));
    }

    public function test_license_other_is_only_sent_for_other_licenses(): void
    {
        $this->dataset(['license' => 'CC0-1.0', 'license_other' => 'stale value']);

        $this->getJson('/api/catalogue')->assertJsonPath('data.0.license_other', null);
    }

    // ---- GET /api/catalogue/{id} ----------------------------------------

    public function test_detail_returns_published_dataset_with_contact_name_only(): void
    {
        $dataset = $this->dataset([
            'lineage' => 'Digitasi dari peta desa.',
            'usage_constraints' => 'Cantumkan sumber.',
            'contact_name' => 'Rina',
            'contact_email' => 'rina@example.org',
        ]);

        $data = $this->getJson("/api/catalogue/{$dataset->id}")
            ->assertOk()
            ->assertJsonPath('data.lineage', 'Digitasi dari peta desa.')
            ->assertJsonPath('data.usage_constraints', 'Cantumkan sumber.')
            ->assertJsonPath('data.contact_name', 'Rina')
            ->json('data');

        $this->assertArrayNotHasKey('contact_email', $data);
    }

    public function test_detail_returns_404_for_every_unpublished_state(): void
    {
        foreach ($this->unpublished() as $state => $dataset) {
            $this->getJson("/api/catalogue/{$dataset->id}")
                ->assertNotFound()
                ->assertExactJson(['message' => 'Dataset not found.']);
        }

        $this->getJson('/api/catalogue/'.Str::uuid())->assertNotFound()->assertExactJson(['message' => 'Dataset not found.']);
    }

    public function test_reopened_dataset_disappears_from_catalogue(): void
    {
        $dataset = $this->dataset();

        $this->getJson("/api/catalogue/{$dataset->id}")->assertOk();

        $this->postJson("/api/datasets/{$dataset->id}/curation/reopen", ['reviewer_name' => 'QA'])->assertOk();

        $this->getJson('/api/catalogue')->assertJsonCount(0, 'data');
        $this->getJson("/api/catalogue/{$dataset->id}")->assertNotFound();
        // The internal API is unaffected.
        $this->getJson("/api/datasets/{$dataset->id}")->assertOk();
    }

    // ---- Public preview --------------------------------------------------

    public function test_public_vector_preview_serves_published_dataset(): void
    {
        $this->createTable();
        $dataset = $this->dataset(['postgis_schema' => 'ingest', 'postgis_table' => self::TABLE]);

        $this->get("/api/catalogue/{$dataset->id}/preview/vector")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/geo+json')
            ->assertJsonPath('features.0.properties.nama', 'Desa 1');
    }

    public function test_public_vector_preview_hides_unpublished_datasets(): void
    {
        $this->createTable();

        foreach (['pending', 'rejected'] as $status) {
            $dataset = $this->dataset(['curation_status' => $status, 'postgis_schema' => 'ingest', 'postgis_table' => self::TABLE]);

            $this->getJson("/api/catalogue/{$dataset->id}/preview/vector")
                ->assertNotFound()
                ->assertExactJson(['message' => 'Dataset not found.']);

            // The internal preview keeps working for curators.
            $this->get("/api/datasets/{$dataset->id}/preview/vector")->assertOk();
        }
    }

    public function test_public_raster_info_points_to_public_cog_url(): void
    {
        $dataset = $this->raster();

        $this->getJson("/api/catalogue/{$dataset->id}/preview/raster/info")
            ->assertOk()
            ->assertJsonPath('data.url', "/api/catalogue/{$dataset->id}/preview/raster");

        // Internal info still points to the internal route.
        $this->getJson("/api/datasets/{$dataset->id}/preview/raster/info")
            ->assertJsonPath('data.url', "/api/datasets/{$dataset->id}/preview/raster");
    }

    public function test_public_raster_supports_range_requests(): void
    {
        $dataset = $this->raster();

        $this->get("/api/catalogue/{$dataset->id}/preview/raster", ['Range' => 'bytes=0-99'])
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-99/4096');
    }

    public function test_public_raster_hides_unpublished_datasets(): void
    {
        $dataset = $this->raster(['curation_status' => 'pending']);

        $this->getJson("/api/catalogue/{$dataset->id}/preview/raster")->assertNotFound();
        $this->getJson("/api/catalogue/{$dataset->id}/preview/raster/info")->assertNotFound();
        $this->getJson('/api/catalogue/'.Str::uuid().'/preview/raster/info')->assertNotFound()->assertExactJson(['message' => 'Dataset not found.']);
    }
}
