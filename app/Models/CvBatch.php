<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CvBatch extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'template_path',
        'template_original_name',
        'source_folder',
        'status',
        'total_files',
        'processed_files',
        'successful_files',
        'failed_files',
        'confirmed_at',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CvBatchItem::class);
    }
}
