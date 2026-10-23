<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One figure in a specific (count) or ranked (priority) auto-lock rule. */
class AutoLockRulePick extends Model
{
    public $timestamps = false;

    protected $fillable = ['auto_lock_rule_id', 'sku_id', 'count', 'priority', 'locks_made'];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutoLockRule::class, 'auto_lock_rule_id');
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
