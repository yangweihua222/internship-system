<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingEventRecord extends Model
{
    protected $fillable = ['teaching_event_id', 'user_id', 'sign_in_at', 'sign_out_at', 'score', 'comment'];

    protected function casts(): array
    {
        return ['sign_in_at' => 'datetime', 'sign_out_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(TeachingEvent::class, 'teaching_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
