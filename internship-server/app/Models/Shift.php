<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time', 'late_threshold_minutes'];

    public function schedules(): HasMany
    {
        return $this->hasMany(AttendanceSchedule::class);
    }
}
