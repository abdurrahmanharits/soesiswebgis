<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetCurationEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'dataset_id',
        'user_id',
        'action',
        'reviewer_name',
        'note',
        'from_status',
        'to_status',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /** Null for events recorded before authentication (reviewer_name is then the only identity). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
