<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSchedule extends Model
{
    protected $fillable = ['user_id', 'work_date', 'shift_id', 'group_id'];

    protected function casts(): array
    {
        return ['work_date' => 'date:Y-m-d'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AttendanceGroup::class, 'group_id');
    }
}
