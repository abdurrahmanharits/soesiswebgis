<?php

namespace App\Jobs;

use App\Models\Dataset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class ProcessSpatialDataset implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;
    public int $tries = 2;

    public function __construct(
        public string $datasetId
    ) {
        $this->onQueue('spatial');
    }

    public function handle(): void
    {
        $dataset = Dataset::findOrFail($this->datasetId);

        $dataset->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            if ($dataset->format === 'shapefile') {
                $this->processShapefile($dataset);
            } elseif ($dataset->format === 'geotiff') {
                $this->processGeoTiff($dataset);
            } else {
                throw new \RuntimeException(
                    "Format tidak didukung: {$dataset->format}"
                );
            }

            $dataset->update([
                'status' => 'ready_for_curation',
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            $dataset->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function processShapefile(Dataset $dataset): void
    {
        $zipPath = Storage::disk('local')->path(
            $dataset->original_path
        );

        if (! file_exists($zipPath)) {
            throw new \RuntimeException(
                "File ZIP tidak ditemukan: {$zipPath}"
            );
        }

        $workDir = storage_path(
            "app/private/work/{$dataset->id}"
        );

        if (! is_dir($workDir)) {
            mkdir($workDir, 0755, true);
        }

        /*
        |--------------------------------------------------------------------------
        | Extract ZIP
        |--------------------------------------------------------------------------
        */

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException(
                'ZIP Shapefile tidak dapat dibuka.'
            );
        }

        $zip->extractTo($workDir);
        $zip->close();

        /*
        |--------------------------------------------------------------------------
        | Cari file SHP
        |--------------------------------------------------------------------------
        */

        $shpFiles = glob(
            $workDir . DIRECTORY_SEPARATOR . '*.shp'
        );

        if ($shpFiles === false || count($shpFiles) !== 1) {
            throw new \RuntimeException(
                'ZIP harus mengandung tepat satu file .shp.'
            );
        }

        $shpPath = $shpFiles[0];

        $baseName = pathinfo(
            $shpPath,
            PATHINFO_FILENAME
        );

        /*
        |--------------------------------------------------------------------------
        | Validasi komponen Shapefile
        |--------------------------------------------------------------------------
        */

        foreach (['shx', 'dbf', 'prj'] as $extension) {
            $requiredFile =
                $workDir .
                DIRECTORY_SEPARATOR .
                $baseName .
                '.' .
                $extension;

            if (! file_exists($requiredFile)) {
                throw new \RuntimeException(
                    "Komponen .{$extension} tidak ditemukan."
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Ekstraksi metadata dengan ogrinfo
        |--------------------------------------------------------------------------
        */

        $metadataProcess = new Process([
            'ogrinfo',
            '-json',
            '-so',
            $shpPath,
        ]);

        $metadataProcess->setTimeout(300);
        $metadataProcess->mustRun();

        $metadata = json_decode(
            $metadataProcess->getOutput(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $layer = $metadata['layers'][0] ?? null;

        if (! is_array($layer)) {
            throw new \RuntimeException(
                'Metadata layer Shapefile tidak ditemukan.'
            );
        }

        $geometryField =
            $layer['geometryFields'][0] ?? [];

        /*
        |--------------------------------------------------------------------------
        | CRS / SRID
        |--------------------------------------------------------------------------
        */

        $crs = null;
        $srid = null;

        $coordinateSystem =
            $geometryField['coordinateSystem'] ?? [];

        $authority =
            $coordinateSystem['projjson']['id']['authority']
            ?? null;

        $code =
            $coordinateSystem['projjson']['id']['code']
            ?? null;

        if ($authority !== null && $code !== null) {
            $srid = (int) $code;
            $crs = "{$authority}:{$srid}";
        }

        /*
        |--------------------------------------------------------------------------
        | Simpan metadata Shapefile
        |--------------------------------------------------------------------------
        */

        $dataset->update([
            'crs' => $crs,
            'srid' => $srid,
            'geometry_type' =>
                $geometryField['type'] ?? null,
            'feature_count' =>
                $layer['featureCount'] ?? null,
            'bbox' =>
                $geometryField['extent'] ?? null,
            'attribute_schema' =>
                $layer['fields'] ?? null,
            'metadata' =>
                $metadata,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Import ke PostGIS
        |--------------------------------------------------------------------------
        */

        $tableName =
            'ds_' .
            str_replace('-', '', $dataset->id);

        $connection = sprintf(
            'PG:host=%s port=%s dbname=%s user=%s password=%s',
            config('database.connections.pgsql.host'),
            config('database.connections.pgsql.port'),
            config('database.connections.pgsql.database'),
            config('database.connections.pgsql.username'),
            config('database.connections.pgsql.password')
        );

        $importProcess = new Process([
            'ogr2ogr',
            '-f',
            'PostgreSQL',
            $connection,
            $shpPath,
            '-nln',
            "ingest.{$tableName}",
            '-lco',
            'GEOMETRY_NAME=geom',
            '-nlt',
            'PROMOTE_TO_MULTI',
            '-makevalid',
            '-overwrite',
        ]);

        $importProcess->setTimeout(600);
        $importProcess->mustRun();

        $dataset->update([
            'postgis_schema' => 'ingest',
            'postgis_table' => $tableName,
        ]);
    }

    private function processGeoTiff(Dataset $dataset): void
    {
        $path = Storage::disk('local')->path(
            $dataset->original_path
        );

        if (! file_exists($path)) {
            throw new \RuntimeException(
                "GeoTIFF tidak ditemukan: {$path}"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Ekstraksi metadata GeoTIFF
        |--------------------------------------------------------------------------
        */

        $metadataProcess = new Process([
            'gdalinfo',
            '-json',
            $path,
        ]);

        $metadataProcess->setTimeout(300);
        $metadataProcess->mustRun();

        $metadata = json_decode(
            $metadataProcess->getOutput(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        /*
        |--------------------------------------------------------------------------
        | Ukuran raster
        |--------------------------------------------------------------------------
        */

        $size = $metadata['size'] ?? [
            null,
            null,
        ];

        $rasterWidth =
            isset($size[0])
                ? (int) $size[0]
                : null;

        $rasterHeight =
            isset($size[1])
                ? (int) $size[1]
                : null;

        /*
        |--------------------------------------------------------------------------
        | Band
        |--------------------------------------------------------------------------
        */

        $bands = $metadata['bands'] ?? [];

        if (! is_array($bands)) {
            $bands = [];
        }

        $bandCount = count($bands);

        /*
        |--------------------------------------------------------------------------
        | CRS / EPSG
        |--------------------------------------------------------------------------
        */

        $crs = null;
        $srid = null;

        /*
         * Coba terlebih dahulu membaca EPSG langsung
         * dari output JSON gdalinfo.
         */
        $coordinateSystem =
            $metadata['coordinateSystem'] ?? [];

        $authority =
            $coordinateSystem['projjson']['id']['authority']
            ?? null;

        $code =
            $coordinateSystem['projjson']['id']['code']
            ?? null;

        if ($authority !== null && $code !== null) {
            $srid = (int) $code;
            $crs = "{$authority}:{$srid}";
        }

        /*
         * Jika belum ditemukan, gunakan gdalsrsinfo
         * sebagai fallback.
         */
        if ($srid === null) {
            $srsProcess = new Process([
                'gdalsrsinfo',
                '-o',
                'epsg',
                $path,
            ]);

            $srsProcess->setTimeout(60);
            $srsProcess->run();

            if ($srsProcess->isSuccessful()) {
                $srsOutput = trim(
                    $srsProcess->getOutput()
                );

                if (
                    preg_match(
                        '/EPSG[:"]+\s*(\d+)/i',
                        $srsOutput,
                        $matches
                    )
                ) {
                    $srid = (int) $matches[1];
                    $crs = "EPSG:{$srid}";
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Pixel size
        |--------------------------------------------------------------------------
        */

        $geoTransform =
            $metadata['geoTransform'] ?? null;

        $pixelSizeX = null;
        $pixelSizeY = null;

        if (
            is_array($geoTransform) &&
            isset($geoTransform[1])
        ) {
            $pixelSizeX =
                abs((float) $geoTransform[1]);
        }

        if (
            is_array($geoTransform) &&
            isset($geoTransform[5])
        ) {
            $pixelSizeY =
                abs((float) $geoTransform[5]);
        }

        /*
        |--------------------------------------------------------------------------
        | NoData
        |--------------------------------------------------------------------------
        */

        $nodata = [];

        foreach ($bands as $band) {
            $nodata[] =
                $band['noDataValue'] ?? null;
        }

        /*
        |--------------------------------------------------------------------------
        | Generate Cloud Optimized GeoTIFF
        |--------------------------------------------------------------------------
        */

        $outputDirectory = storage_path(
            "app/private/processed/{$dataset->id}"
        );

        if (! is_dir($outputDirectory)) {
            if (
                ! mkdir(
                    $outputDirectory,
                    0755,
                    true
                ) &&
                ! is_dir($outputDirectory)
            ) {
                throw new \RuntimeException(
                    "Tidak dapat membuat direktori: {$outputDirectory}"
                );
            }
        }

        $outputPath =
            $outputDirectory .
            DIRECTORY_SEPARATOR .
            'cog.tif';

        $cogProcess = new Process([
            'gdal_translate',
            $path,
            $outputPath,
            '-of',
            'COG',
            '-co',
            'COMPRESS=DEFLATE',
            '-co',
            'BIGTIFF=IF_SAFER',
        ]);

        $cogProcess->setTimeout(900);
        $cogProcess->mustRun();

        if (! file_exists($outputPath)) {
            throw new \RuntimeException(
                'File COG gagal dibuat.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Simpan metadata raster
        |--------------------------------------------------------------------------
        */

        $dataset->update([
            'crs' => $crs,
            'srid' => $srid,
            'raster_width' => $rasterWidth,
            'raster_height' => $rasterHeight,
            'band_count' => $bandCount,
            'pixel_size_x' => $pixelSizeX,
            'pixel_size_y' => $pixelSizeY,
            'nodata' => $nodata,
            'processed_path' =>
                "processed/{$dataset->id}/cog.tif",
            'metadata' => $metadata,
        ]);
    }
}