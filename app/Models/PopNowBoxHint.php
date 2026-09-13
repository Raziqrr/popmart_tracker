<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PopNowBoxHint extends Model
{
    protected $fillable = [
        'pop_now_box_id', 'sku_id', 'source', 'reported_by_user_id',
        'confirmations', 'first_seen_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(PopNowBox::class, 'pop_now_box_id');
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }
}
