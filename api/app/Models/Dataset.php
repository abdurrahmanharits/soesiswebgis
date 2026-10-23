<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dataset extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'original_filename',
        'format',
        'status',
        'original_path',
        'processed_path',
        'postgis_schema',
        'postgis_table',
        'crs',
        'srid',
        'geometry_type',
        'feature_count',
        'bbox',
        'attribute_schema',
        'raster_width',
        'raster_height',
        'band_count',
        'pixel_size_x',
        'pixel_size_y',
        'nodata',
        'metadata',
        'error_message',

        // Curation
        'curation_status',
        'description',
        'keywords',
        'topic_category',
        'source',
        'source_organization',
        'license',
        'license_other',
        'usage_constraints',
        'lineage',
        'temporal_start',
        'temporal_end',
        'geographic_description',
        'contact_name',
        'contact_email',
    ];

    protected function casts(): array
    {
        return [
            'bbox' => 'array',
            'attribute_schema' => 'array',
            'nodata' => 'array',
            'metadata' => 'array',
            'keywords' => 'array',
            'temporal_start' => 'date:Y-m-d',
            'temporal_end' => 'date:Y-m-d',
        ];
    }

    public function curationEvents(): HasMany
    {
        return $this->hasMany(DatasetCurationEvent::class)->latest('id');
    }
}
