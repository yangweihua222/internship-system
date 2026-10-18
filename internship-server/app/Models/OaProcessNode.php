<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OaProcessNode extends Model
{
    protected $fillable = ['process_id', 'node_order', 'approver_id', 'status', 'comment', 'acted_at'];

    protected function casts(): array
    {
        return ['acted_at' => 'datetime'];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(OaProcess::class, 'process_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
