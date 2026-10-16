<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CvBatchItem extends Model
{
    protected $fillable = [
        'cv_batch_id',
        'source_item_id',
        'drive_id',
        'source_filename',
        'source_path',
        'source_web_url',
        'status',
        'output_path',
        'output_filename',
        'error_message',
        'validation_warnings',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'validation_warnings' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CvBatch::class, 'cv_batch_id');
    }
}
