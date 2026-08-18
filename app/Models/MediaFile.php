<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaFile extends Model
{
    protected $fillable = [
        'original_name',
        'file_path',
        'file_hash',
        'mime_type',
        'file_size',
    ];

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
