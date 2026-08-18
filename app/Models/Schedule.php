<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = [
        'project_id',
        'item_code',
        'portfolio_name',
        'media_file_id',
        'media_path',
        'media_paths',
        'target_date',
        'target_time',
        'status',
        'notes',
    ];

    protected $casts = [
        'target_date' => 'date',
        'media_paths' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }
}
