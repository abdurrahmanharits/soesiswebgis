<?php

namespace App\Support;

use App\Models\Dataset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The public view of a dataset: only published (ingested + approved) datasets,
 * serialized through an explicit whitelist. Never exposes storage paths,
 * PostGIS identifiers, raw GDAL/OGR metadata, curation notes, reviewers or
 * contact e-mail addresses.
 */
class CatalogueDataset
{
    public static function isPublished(Dataset $dataset): bool
    {
        return $dataset->status === 'ready_for_curation' && $dataset->curation_status === 'approved';
    }

    public static function query(): Builder
    {
        return Dataset::query()
            ->where('status', 'ready_for_curation')
            ->where('curation_status', 'approved')
            ->select([
                'id', 'title', 'original_filename', 'format', 'crs', 'srid', 'bbox', 'geometry_type',
                'feature_count', 'band_count', 'description', 'keywords', 'topic_category', 'source',
                'source_organization', 'license', 'license_other', 'usage_constraints', 'lineage',
                'temporal_start', 'temporal_end', 'geographic_description', 'contact_name', 'created_at',
            ])
            // Only the extent part of the (possibly large) metadata document.
            ->addSelect(DB::raw("metadata->'wgs84Extent' as wgs84_extent"))
            // Latest approval = when the current version was published.
            ->addSelect(DB::raw(
                "(select max(e.created_at) from dataset_curation_events e where e.dataset_id = datasets.id and e.action = 'approved') as published_at"
            ));
    }

    public static function find(string $id): ?Dataset
    {
        return self::query()->whereKey($id)->first();
    }

    /** Catalogue list item: everything the cards and client-side search/filter need. */
    public static function summary(Dataset $dataset): array
    {
        $publishedAt = $dataset->getRawOriginal('published_at');

        return [
            'id' => $dataset->id,
            'title' => $dataset->title ?: $dataset->original_filename,
            'description' => $dataset->description,
            'format' => $dataset->format,
            'crs' => $dataset->crs,
            'extent' => $dataset->format === 'geotiff'
                ? DatasetExtent::fromWgs84Polygon(json_decode($dataset->getRawOriginal('wgs84_extent') ?? 'null', true))
                : DatasetExtent::fromBbox($dataset->bbox, $dataset->srid),
            'geometry_type' => $dataset->geometry_type,
            'feature_count' => $dataset->feature_count,
            'band_count' => $dataset->band_count,
            'keywords' => array_values($dataset->keywords ?? []),
            'topic_category' => $dataset->topic_category,
            'source' => $dataset->source,
            'source_organization' => $dataset->source_organization,
            'license' => $dataset->license,
            'license_other' => $dataset->license === 'other' ? $dataset->license_other : null,
            'temporal_start' => $dataset->temporal_start?->format('Y-m-d'),
            'temporal_end' => $dataset->temporal_end?->format('Y-m-d'),
            'geographic_description' => $dataset->geographic_description,
            'created_at' => $dataset->created_at?->toJSON(),
            'published_at' => $publishedAt ? Carbon::parse($publishedAt)->toJSON() : null,
        ];
    }

    /** Public detail: the summary plus the longer descriptive fields and the contact name. */
    public static function detail(Dataset $dataset): array
    {
        return [
            ...self::summary($dataset),
            'usage_constraints' => $dataset->usage_constraints,
            'lineage' => $dataset->lineage,
            'contact_name' => $dataset->contact_name,
        ];
    }
}
