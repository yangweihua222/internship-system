<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OaShiftChangeDetail extends Model
{
    protected $fillable = ['process_id', 'my_schedule_id', 'target_schedule_id', 'reason'];

    public function mySchedule(): BelongsTo
    {
        return $this->belongsTo(DutySchedule::class, 'my_schedule_id');
    }

    public function targetSchedule(): BelongsTo
    {
        return $this->belongsTo(DutySchedule::class, 'target_schedule_id');
    }
}
