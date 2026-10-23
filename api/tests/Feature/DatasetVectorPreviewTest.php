<?php

namespace Tests\Feature;

use App\Models\Dataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatasetVectorPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const TABLE = 'ds_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private function dataset(array $attributes = []): Dataset
    {
        return Dataset::create([
            'title' => 'Test vector',
            'original_filename' => 'test.zip',
            'format' => 'shapefile',
            'status' => 'ready_for_curation',
            'postgis_schema' => 'ingest',
            'postgis_table' => self::TABLE,
            'crs' => 'EPSG:4326',
            'srid' => 4326,
            'bbox' => [96.0, 4.0, 96.1, 4.1],
            ...$attributes,
        ]);
    }

    /**
     * Mirrors what ogr2ogr creates: ogc_fid, geom (MULTI*), attribute columns.
     * Created inside the test transaction, so it is rolled back afterwards.
     */
    private function createTable(int $features, int $srid = 4326, array $origin = [96.0, 4.0], float $step = 0.01): void
    {
        DB::statement('create schema if not exists ingest');
        DB::statement('create table ingest.'.self::TABLE." (ogc_fid serial primary key, geom geometry(MultiPolygon, {$srid}), nama varchar)");

        for ($i = 0; $i < $features; $i++) {
            $x = $origin[0] + $i * $step;
            $y = $origin[1];

            DB::insert(
                'insert into ingest.'.self::TABLE." (geom, nama) values (ST_Multi(ST_MakeEnvelope(?, ?, ?, ?, {$srid})), ?)",
                [$x, $y, $x + $step / 2, $y + $step / 2, "Desa {$i}"],
            );
        }
    }

    private function url(Dataset|string $dataset): string
    {
        $id = $dataset instanceof Dataset ? $dataset->id : $dataset;

        return "/api/datasets/{$id}/preview/vector";
    }

    public function test_unknown_dataset_returns_404(): void
    {
        $this->getJson($this->url((string) Str::uuid()))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Dataset not found.']);
    }

    public function test_wrong_format_returns_422(): void
    {
        $this->getJson($this->url($this->dataset(['format' => 'geotiff'])))
            ->assertStatus(422);
    }

    public function test_dataset_not_ready_returns_409(): void
    {
        $this->getJson($this->url($this->dataset(['status' => 'processing'])))
            ->assertStatus(409)
            ->assertJson(['message' => 'Dataset is not ready for preview.']);
    }

    public function test_missing_postgis_table_returns_404(): void
    {
        $this->getJson($this->url($this->dataset()))
            ->assertNotFound()
            ->assertJson(['message' => 'Spatial table for this dataset was not found.']);
    }

    public function test_invalid_table_identifier_is_rejected(): void
    {
        $dataset = $this->dataset(['postgis_table' => 'ds_x"; drop table datasets; --']);

        $this->getJson($this->url($dataset))
            ->assertStatus(500)
            ->assertJson(['message' => 'Spatial table reference for this dataset is invalid.']);

        $this->assertDatabaseHas('datasets', ['id' => $dataset->id]);
    }

    public function test_returns_geojson_feature_collection(): void
    {
        $this->createTable(2);

        $response = $this->get($this->url($this->dataset()))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/geo+json');

        $body = $response->json();

        $this->assertSame('FeatureCollection', $body['type']);
        $this->assertSame(['limit' => 10000, 'total' => 2, 'truncated' => false], $body['preview']);
        $this->assertEqualsWithDelta([96.0, 4.0, 96.1, 4.1], $body['bbox'], 1e-9);
        $this->assertCount(2, $body['features']);

        $feature = collect($body['features'])->firstWhere('id', 1);

        $this->assertSame('MultiPolygon', $feature['geometry']['type']);
        // Only attribute columns: no geometry, no OGR FID.
        $this->assertSame(['nama' => 'Desa 0'], $feature['properties']);
    }

    public function test_truncates_to_feature_limit(): void
    {
        config(['spatial.preview_vector_limit' => 2]);
        $this->createTable(3);

        $body = $this->get($this->url($this->dataset()))->assertOk()->json();

        $this->assertCount(2, $body['features']);
        $this->assertSame(['limit' => 2, 'total' => 3, 'truncated' => true], $body['preview']);
    }

    public function test_reprojects_non_4326_data_to_wgs84(): void
    {
        // WGS 84 / UTM zone 50S (metres), e.g. Kalimantan.
        $this->createTable(1, 32750, [500000, 9900000], 1000);

        $dataset = $this->dataset([
            'crs' => 'EPSG:32750',
            'srid' => 32750,
            'bbox' => [500000, 9900000, 500500, 9900500],
        ]);

        $body = $this->get($this->url($dataset))->assertOk()->json();

        [$lon, $lat] = $body['features'][0]['geometry']['coordinates'][0][0][0];

        $this->assertEqualsWithDelta(117.0, $lon, 0.01);
        $this->assertEqualsWithDelta(-0.9, $lat, 0.01);
        $this->assertEqualsWithDelta(117.0, $body['bbox'][0], 0.01);
    }
}
