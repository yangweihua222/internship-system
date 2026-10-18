<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckinDayRecord extends Model
{
    public const STATUS_NORMAL = 'normal';
    public const STATUS_LATE = 'late';
    public const STATUS_EARLY = 'early';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LEAVE = 'leave';
    public const STATUS_UNKNOWN = 'unknown';

    protected $fillable = [
        'user_id', 'work_date', 'check_in_at', 'check_out_at',
        'status', 'late_minutes', 'remark',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
