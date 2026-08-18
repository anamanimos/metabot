<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaAccount extends Model
{
    protected $fillable = [
        'account_name',
        'session_folder',
        'status',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }
}
