<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Support\DatasetExtent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Lightweight layer catalogue for the /map page: ingested datasets that are
 * approved or still pending review (rejected ones are excluded). Never returns
 * raw GDAL/OGR metadata, storage paths or PostGIS identifiers.
 */
class MapLayerController extends Controller
{
    public const CURATION_STATUSES = ['approved', 'pending'];

    public function index(): JsonResponse
    {
        $datasets = Dataset::query()
            ->where('status', 'ready_for_curation')
            ->whereIn('curation_status', self::CURATION_STATUSES)
            ->select([
                'id', 'title', 'original_filename', 'format', 'crs', 'srid', 'bbox', 'curation_status',
                'geometry_type', 'feature_count', 'band_count', 'topic_category', 'source_organization',
                'license', 'license_other', 'temporal_start', 'temporal_end',
            ])
            // Only the extent part of the (possibly large) metadata document.
            ->addSelect(DB::raw("metadata->'wgs84Extent' as wgs84_extent"))
            ->orderByRaw("case when curation_status = 'approved' then 0 else 1 end")
            ->orderBy('title')
            ->get();

        return response()->json([
            'data' => $datasets->map(fn (Dataset $dataset) => [
                'id' => $dataset->id,
                'title' => $dataset->title ?: $dataset->original_filename,
                'format' => $dataset->format,
                'crs' => $dataset->crs,
                'extent' => $dataset->format === 'geotiff'
                    ? DatasetExtent::fromWgs84Polygon(json_decode($dataset->getRawOriginal('wgs84_extent') ?? 'null', true))
                    : DatasetExtent::fromBbox($dataset->bbox, $dataset->srid),
                'curation_status' => $dataset->curation_status,
                'geometry_type' => $dataset->geometry_type,
                'feature_count' => $dataset->feature_count,
                'band_count' => $dataset->band_count,
                'topic_category' => $dataset->topic_category,
                'source_organization' => $dataset->source_organization,
                'license' => $dataset->license === 'other' ? $dataset->license_other : $dataset->license,
                'temporal_start' => $dataset->temporal_start?->format('Y-m-d'),
                'temporal_end' => $dataset->temporal_end?->format('Y-m-d'),
            ]),
        ]);
    }
}
