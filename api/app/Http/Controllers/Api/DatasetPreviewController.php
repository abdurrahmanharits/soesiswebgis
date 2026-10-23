<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Support\DatasetExtent;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\PathTraversalDetected;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Read-only preview endpoints for the OpenLayers map on the dataset detail page.
 * Never modifies datasets, PostGIS tables or processed files.
 */
class DatasetPreviewController extends Controller
{
    private const SCHEMA_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    private const TABLE_PATTERN = '/^ds_[A-Fa-f0-9]{32}$/';

    /**
     * GeoJSON FeatureCollection (EPSG:4326) of the dataset's PostGIS table.
     */
    public function vector(Dataset $dataset): Response
    {
        $this->ensurePreviewable($dataset, 'shapefile');

        $schema = $dataset->postgis_schema;
        $table = $dataset->postgis_table;

        if (! is_string($schema) || ! is_string($table)) {
            $this->fail(404, 'Spatial table for this dataset was not found.');
        }

        if (! preg_match(self::SCHEMA_PATTERN, $schema) || ! preg_match(self::TABLE_PATTERN, $table)) {
            Log::error('Invalid PostGIS identifier on dataset.', ['dataset' => $dataset->id]);
            $this->fail(500, 'Spatial table reference for this dataset is invalid.');
        }

        $geometryColumn = DB::selectOne(
            'select f_geometry_column as name, srid from geometry_columns where f_table_schema = ? and f_table_name = ? limit 1',
            [$schema, $table],
        );

        if (! $geometryColumn) {
            $this->fail(404, 'Spatial table for this dataset was not found.');
        }

        if ((int) $geometryColumn->srid <= 0) {
            $this->fail(422, 'Dataset CRS is unknown and cannot be previewed safely.');
        }

        if (! preg_match(self::SCHEMA_PATTERN, $geometryColumn->name)) {
            $this->fail(500, 'Geometry column for this dataset is invalid.');
        }

        $source = "\"{$schema}\".\"{$table}\"";
        $geom = "\"{$geometryColumn->name}\"";
        $limit = max(1, (int) config('spatial.preview_vector_limit'));

        $total = (int) DB::selectOne("select count(*) as total from {$source}")->total;

        // Properties = every attribute column except the geometry and OGR's internal FID.
        $features = DB::selectOne(
            "select coalesce(json_agg(s.feature), '[]'::json) as features from (
                select json_build_object(
                    'type', 'Feature',
                    'id', to_jsonb(t) -> 'ogc_fid',
                    'geometry', ST_AsGeoJSON(ST_Transform(t.{$geom}, 4326), 7)::json,
                    'properties', to_jsonb(t) - ?::text - 'ogc_fid'
                ) as feature
                from {$source} t
                limit ?
            ) s",
            [$geometryColumn->name, $limit],
        )->features;

        $collection = array_filter([
            'type' => 'FeatureCollection',
            'bbox' => DatasetExtent::fromBbox($dataset->bbox, (int) $geometryColumn->srid),
            'preview' => [
                'limit' => $limit,
                'total' => $total,
                'truncated' => $total > $limit,
            ],
        ], fn ($value) => $value !== null);

        // Splice the database-built features array in without decoding it in PHP.
        $body = substr(json_encode($collection), 0, -1).',"features":'.$features.'}';

        return response($body, 200, ['Content-Type' => 'application/geo+json']);
    }

    /**
     * The processed Cloud Optimized GeoTIFF, with HTTP Range support (206/416)
     * handled by BinaryFileResponse, which streams from the requested offset.
     */
    public function raster(Dataset $dataset): BinaryFileResponse
    {
        $this->ensurePreviewable($dataset, 'geotiff');

        $response = new BinaryFileResponse(
            $this->rasterPath($dataset),
            200,
            ['Content-Type' => 'image/tiff'],
            public: false,
        );

        $response->setPrivate();
        $response->setMaxAge(3600);

        return $response;
    }

    /**
     * What the client needs to render the COG: CRS definition, extent and band statistics.
     */
    public function rasterInfo(Dataset $dataset): JsonResponse
    {
        $this->ensurePreviewable($dataset, 'geotiff');

        $path = $this->rasterPath($dataset);

        if (! $dataset->srid || ! is_string($dataset->crs) || ! str_starts_with($dataset->crs, 'EPSG:')) {
            $this->fail(422, 'Dataset CRS is unknown and cannot be previewed safely.');
        }

        $proj4 = DB::selectOne('select proj4text from spatial_ref_sys where srid = ?', [$dataset->srid])?->proj4text;

        if (! $proj4) {
            $this->fail(422, "CRS {$dataset->crs} is not supported for preview.");
        }

        return response()->json([
            'data' => [
                'url' => route('api.datasets.preview.raster', $dataset, absolute: false),
                'crs' => $dataset->crs,
                'srid' => $dataset->srid,
                'proj4' => trim($proj4),
                'extent' => DatasetExtent::fromWgs84Polygon($dataset->metadata['wgs84Extent'] ?? null),
                'nodata' => $dataset->nodata,
                'band_count' => $dataset->band_count,
                'statistics' => $this->rasterStatistics($dataset, $path),
            ],
        ]);
    }

    private function ensurePreviewable(Dataset $dataset, string $format): void
    {
        if ($dataset->format !== $format) {
            $this->fail(422, "This preview is only available for {$format} datasets.");
        }

        if ($dataset->status !== 'ready_for_curation') {
            $this->fail(409, 'Dataset is not ready for preview.');
        }
    }

    private function rasterPath(Dataset $dataset): string
    {
        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));

        try {
            $path = $dataset->processed_path ? realpath($disk->path($dataset->processed_path)) : false;
        } catch (PathTraversalDetected) {
            $path = false;
        }

        if ($root === false || $path === false || ! is_file($path) || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
            $this->fail(404, 'Processed raster file was not found.');
        }

        return $path;
    }

    /**
     * Approximate band 1 statistics, computed once per file and cached.
     * GDAL_PAM_ENABLED=NO keeps gdalinfo from writing a .aux.xml next to the COG.
     */
    private function rasterStatistics(Dataset $dataset, string $path): ?array
    {
        $key = "datasets:{$dataset->id}:raster-statistics:".filemtime($path);

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $result = Process::env(['GDAL_PAM_ENABLED' => 'NO'])
            ->timeout(300)
            ->run(['gdalinfo', '-json', '-approx_stats', $path]);

        if ($result->failed()) {
            Log::warning('Raster statistics failed.', ['dataset' => $dataset->id, 'error' => $result->errorOutput()]);

            return null;
        }

        $band = json_decode($result->output(), true)['bands'][0] ?? [];
        $minimum = $band['minimum'] ?? $band['metadata']['']['STATISTICS_MINIMUM'] ?? null;
        $maximum = $band['maximum'] ?? $band['metadata']['']['STATISTICS_MAXIMUM'] ?? null;

        if (! is_numeric($minimum) || ! is_numeric($maximum)) {
            return null;
        }

        $statistics = [
            'minimum' => (float) $minimum,
            'maximum' => (float) $maximum,
            'approximate' => true,
        ];

        Cache::forever($key, $statistics);

        return $statistics;
    }

    private function fail(int $status, string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], $status));
    }
}
