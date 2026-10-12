<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharePointDocument extends Model
{
    protected $fillable = [
        'site_id',
        'drive_id',
        'item_id',
        'name',
        'extension',
        'mime_type',
        'size',
        'path',
        'web_url',
        'parent_item_id',
        'last_modified_at',
        'etag',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'last_modified_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
