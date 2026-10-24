<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapLayersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Internal endpoints require a signed-in account.
        $this->actingAs(User::factory()->role('uploader')->create());
    }

    private function dataset(array $attributes = []): Dataset
    {
        return Dataset::create([
            'title' => 'Layer',
            'original_filename' => 'layer.zip',
            'format' => 'shapefile',
            'status' => 'ready_for_curation',
            'curation_status' => 'approved',
            'crs' => 'EPSG:4326',
            'srid' => 4326,
            'bbox' => [96.0, 4.0, 96.1, 4.1],
            ...$attributes,
        ]);
    }

    public function test_lists_approved_and_pending_ready_datasets_approved_first(): void
    {
        $this->dataset(['title' => 'B pending', 'curation_status' => 'pending']);
        $this->dataset(['title' => 'Z approved']);
        $this->dataset(['title' => 'A approved']);
        $this->dataset(['title' => 'Rejected', 'curation_status' => 'rejected']);
        $this->dataset(['title' => 'Failed', 'status' => 'failed']);
        $this->dataset(['title' => 'Processing', 'status' => 'processing']);

        $this->getJson('/api/map/layers')
            ->assertOk()
            ->assertJsonPath('data.*.title', ['A approved', 'Z approved', 'B pending'])
            ->assertJsonPath('data.*.curation_status', ['approved', 'approved', 'pending']);
    }

    public function test_returns_only_lightweight_fields(): void
    {
        $this->dataset([
            'original_path' => 'datasets/x/original.zip',
            'processed_path' => 'processed/x/cog.tif',
            'postgis_schema' => 'ingest',
            'postgis_table' => 'ds_x',
            'attribute_schema' => [['name' => 'nama']],
            'metadata' => ['layers' => [['name' => 'raw ogrinfo']]],
        ]);

        $layer = $this->getJson('/api/map/layers')->assertOk()->json('data.0');

        $this->assertSame([
            'id', 'title', 'format', 'crs', 'extent', 'curation_status', 'geometry_type', 'feature_count',
            'band_count', 'topic_category', 'source_organization', 'license', 'temporal_start', 'temporal_end',
        ], array_keys($layer));
    }

    public function test_vector_extent_is_reprojected_to_wgs84(): void
    {
        $this->dataset(['crs' => 'EPSG:32750', 'srid' => 32750, 'bbox' => [500000, 9900000, 501000, 9901000]]);

        $extent = $this->getJson('/api/map/layers')->json('data.0.extent');

        $this->assertEqualsWithDelta(117.0, $extent[0], 0.01);
        $this->assertEqualsWithDelta(-0.9, $extent[1], 0.01);
    }

    public function test_raster_extent_comes_from_stored_wgs84_extent(): void
    {
        $this->dataset([
            'format' => 'geotiff',
            'bbox' => null,
            'metadata' => [
                'wgs84Extent' => [
                    'type' => 'Polygon',
                    'coordinates' => [[[94.75, 6.30], [94.75, 1.78], [98.44, 1.78], [98.44, 6.30], [94.75, 6.30]]],
                ],
            ],
        ]);

        $this->getJson('/api/map/layers')->assertJsonPath('data.0.extent', [94.75, 1.78, 98.44, 6.30]);
    }

    public function test_extent_is_null_when_unknown(): void
    {
        $this->dataset(['title' => 'No bbox', 'bbox' => null]);
        $this->dataset(['title' => 'Unknown srid', 'srid' => 999999]);
        $this->dataset(['title' => 'Raster no metadata', 'format' => 'geotiff', 'bbox' => null]);

        $this->getJson('/api/map/layers')
            ->assertOk()
            ->assertJsonPath('data.*.extent', [null, null, null]);
    }

    public function test_other_license_is_returned_by_name(): void
    {
        $this->dataset(['license' => 'other', 'license_other' => 'Lisensi Pemda']);

        $this->getJson('/api/map/layers')->assertJsonPath('data.0.license', 'Lisensi Pemda');
    }

    public function test_map_page_renders(): void
    {
        $this->withoutVite();

        $this->get('/map')
            ->assertOk()
            ->assertSee('data-page="map.index"', false);
    }
}
