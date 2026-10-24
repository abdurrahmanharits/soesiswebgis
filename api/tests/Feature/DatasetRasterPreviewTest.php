<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatasetRasterPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const COG_PATH = 'processed/test/cog.tif';

    private const FILE_SIZE = 4096;

    protected function setUp(): void
    {
        parent::setUp();

        // Internal endpoints require a signed-in account.
        $this->actingAs(User::factory()->role('uploader')->create());

        Storage::fake('local');
        Storage::disk('local')->put(self::COG_PATH, str_repeat('A', self::FILE_SIZE));
    }

    private function dataset(array $attributes = []): Dataset
    {
        return Dataset::create([
            'title' => 'Test raster',
            'original_filename' => 'dem.tif',
            'format' => 'geotiff',
            'status' => 'ready_for_curation',
            'processed_path' => self::COG_PATH,
            'crs' => 'EPSG:4326',
            'srid' => 4326,
            'band_count' => 1,
            'nodata' => [32767],
            'metadata' => [
                'wgs84Extent' => [
                    'type' => 'Polygon',
                    'coordinates' => [[[94.75, 6.30], [94.75, 1.78], [98.44, 1.78], [98.44, 6.30], [94.75, 6.30]]],
                ],
            ],
            ...$attributes,
        ]);
    }

    private function url(Dataset|string $dataset, string $suffix = ''): string
    {
        $id = $dataset instanceof Dataset ? $dataset->id : $dataset;

        return "/api/datasets/{$id}/preview/raster{$suffix}";
    }

    public function test_unknown_dataset_returns_404(): void
    {
        $this->getJson($this->url((string) Str::uuid()))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Dataset not found.']);
    }

    public function test_wrong_format_returns_422(): void
    {
        $this->getJson($this->url($this->dataset(['format' => 'shapefile'])))->assertStatus(422);
    }

    public function test_dataset_not_ready_returns_409(): void
    {
        $this->getJson($this->url($this->dataset(['status' => 'queued'])))->assertStatus(409);
    }

    public function test_missing_cog_returns_404(): void
    {
        $this->getJson($this->url($this->dataset(['processed_path' => 'processed/missing/cog.tif'])))
            ->assertNotFound()
            ->assertJson(['message' => 'Processed raster file was not found.']);
    }

    public function test_path_outside_storage_is_refused(): void
    {
        $this->getJson($this->url($this->dataset(['processed_path' => '../../../.env'])))
            ->assertNotFound();
    }

    public function test_full_request_returns_whole_file(): void
    {
        $this->get($this->url($this->dataset()))
            ->assertOk()
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Type', 'image/tiff')
            ->assertHeader('Content-Length', (string) self::FILE_SIZE);
    }

    public function test_range_request_returns_partial_content(): void
    {
        $this->get($this->url($this->dataset()), ['Range' => 'bytes=0-1023'])
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Type', 'image/tiff')
            ->assertHeader('Content-Range', 'bytes 0-1023/'.self::FILE_SIZE)
            ->assertHeader('Content-Length', '1024');
    }

    public function test_unsatisfiable_range_returns_416(): void
    {
        $this->get($this->url($this->dataset()), ['Range' => 'bytes=5000-6000'])
            ->assertStatus(416)
            ->assertHeader('Content-Range', 'bytes */'.self::FILE_SIZE);
    }

    public function test_info_returns_extent_crs_and_statistics(): void
    {
        Process::fake([
            '*gdalinfo*' => Process::result(json_encode(['bands' => [['minimum' => -12, 'maximum' => 3351]]])),
        ]);

        $dataset = $this->dataset();

        $this->getJson($this->url($dataset, '/info'))
            ->assertOk()
            ->assertJsonPath('data.url', $this->url($dataset))
            ->assertJsonPath('data.srid', 4326)
            ->assertJsonPath('data.proj4', '+proj=longlat +datum=WGS84 +no_defs')
            ->assertJsonPath('data.extent', [94.75, 1.78, 98.44, 6.30])
            ->assertJsonPath('data.nodata', [32767])
            ->assertJsonPath('data.statistics', ['minimum' => -12, 'maximum' => 3351, 'approximate' => true]);

        Process::assertRan(fn ($process) => $process->command === ['gdalinfo', '-json', '-approx_stats', Storage::disk('local')->path(self::COG_PATH)]
            && $process->environment === ['GDAL_PAM_ENABLED' => 'NO']);
    }

    public function test_info_statistics_are_cached(): void
    {
        Process::fake([
            '*gdalinfo*' => Process::result(json_encode(['bands' => [['minimum' => 0, 'maximum' => 10]]])),
        ]);

        $dataset = $this->dataset();

        $this->getJson($this->url($dataset, '/info'))->assertOk();
        $this->getJson($this->url($dataset, '/info'))->assertOk();

        Process::assertRanTimes(fn ($process) => $process->command[0] === 'gdalinfo', 1);
    }

    public function test_info_without_statistics_still_succeeds(): void
    {
        Process::fake(['*gdalinfo*' => Process::result('', 'gdal error', 1)]);

        $this->getJson($this->url($this->dataset(), '/info'))
            ->assertOk()
            ->assertJsonPath('data.statistics', null);
    }

    public function test_info_without_extent_returns_null_extent(): void
    {
        Process::fake();

        $this->getJson($this->url($this->dataset(['metadata' => []]), '/info'))
            ->assertOk()
            ->assertJsonPath('data.extent', null);
    }

    public function test_info_with_unknown_crs_returns_422(): void
    {
        Process::fake();

        $this->getJson($this->url($this->dataset(['crs' => null, 'srid' => null]), '/info'))
            ->assertStatus(422)
            ->assertJson(['message' => 'Dataset CRS is unknown and cannot be previewed safely.']);
    }
}
