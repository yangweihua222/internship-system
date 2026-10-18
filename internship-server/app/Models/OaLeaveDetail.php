<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OaLeaveDetail extends Model
{
    protected $fillable = ['process_id', 'leave_type', 'start_date', 'end_date', 'reason'];

    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d'];
    }
}
