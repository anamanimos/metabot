<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Portfolio extends Model
{
    protected $fillable = [
        'meta_account_id',
        'name',
        'portfolio_name',
        'asset_name',
        'asset_type',
        'combined_target',
    ];

    public function metaAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAccount::class);
    }
}
