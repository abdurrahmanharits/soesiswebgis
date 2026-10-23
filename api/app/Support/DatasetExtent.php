<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Dataset extents as [minLon, minLat, maxLon, maxLat] in EPSG:4326, derived only
 * from values already stored (never runs GDAL). Returns null when unknown.
 */
class DatasetExtent
{
    /**
     * datasets.bbox ([minX, minY, maxX, maxY] in the source CRS) reprojected by PostGIS.
     */
    public static function fromBbox(mixed $bbox, ?int $srid): ?array
    {
        if (! $srid || $srid <= 0 || ! is_array($bbox) || count($bbox) !== 4 || count(array_filter($bbox, 'is_numeric')) !== 4) {
            return null;
        }

        // Joining spatial_ref_sys yields no row for an unknown SRID instead of an error,
        // which would otherwise abort any surrounding PostgreSQL transaction.
        $extent = DB::selectOne(
            'select ST_XMin(e) as minx, ST_YMin(e) as miny, ST_XMax(e) as maxx, ST_YMax(e) as maxy
             from (select ST_Transform(ST_MakeEnvelope(?, ?, ?, ?, s.srid), 4326) as e
                   from spatial_ref_sys s where s.srid = ?) x',
            [...array_map('floatval', array_values($bbox)), $srid],
        );

        if (! $extent) {
            return null;
        }

        return [(float) $extent->minx, (float) $extent->miny, (float) $extent->maxx, (float) $extent->maxy];
    }

    /**
     * Bounding box of gdalinfo's wgs84Extent GeoJSON polygon.
     */
    public static function fromWgs84Polygon(mixed $polygon): ?array
    {
        $ring = is_array($polygon) ? ($polygon['coordinates'][0] ?? null) : null;

        if (! is_array($ring) || $ring === []) {
            return null;
        }

        $xs = [];
        $ys = [];

        foreach ($ring as $point) {
            if (! is_array($point) || ! is_numeric($point[0] ?? null) || ! is_numeric($point[1] ?? null)) {
                return null;
            }

            $xs[] = (float) $point[0];
            $ys[] = (float) $point[1];
        }

        return [min($xs), min($ys), max($xs), max($ys)];
    }
}
