<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckinLog extends Model
{
    protected $fillable = [
        'user_id', 'work_date', 'check_type', 'checked_at',
        'lat', 'lng', 'distance_m', 'result', 'source',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
