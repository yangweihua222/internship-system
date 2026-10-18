<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DutyLog extends Model
{
    protected $fillable = ['duty_schedule_id', 'author_id', 'duty_date', 'title', 'content'];

    protected function casts(): array
    {
        return ['duty_date' => 'date:Y-m-d'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DutySchedule::class, 'duty_schedule_id');
    }

    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'duty_log_viewers', 'duty_log_id', 'user_id')
            ->withTimestamps();
    }
}
