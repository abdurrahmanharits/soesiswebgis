<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

        // Provenance
        'uploaded_by',
        'organization_id',
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Legacy datasets (uploaded before authentication) have no owner. */
    public function isUploadedBy(User $user): bool
    {
        return $this->uploaded_by !== null && (int) $this->uploaded_by === (int) $user->id;
    }
}
