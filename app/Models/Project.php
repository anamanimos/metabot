<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'meta_account_id',
        'portfolio_name',
        'target_time',
        'images_per_post',
        'exclude_days',
        'is_continuous',
        'status',
    ];

    protected $casts = [
        'exclude_days' => 'array',
        'is_continuous' => 'boolean',
    ];

    public function metaAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAccount::class);
    }

    public function mediaFiles(): BelongsToMany
    {
        return $this->belongsToMany(MediaFile::class, 'project_media')
                    ->withPivot('sort_order')
                    ->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
