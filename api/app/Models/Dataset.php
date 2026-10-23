<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected function casts(): array
    {
        return [
            'bbox' => 'array',
            'attribute_schema' => 'array',
            'nodata' => 'array',
            'metadata' => 'array',
        ];
    }
}