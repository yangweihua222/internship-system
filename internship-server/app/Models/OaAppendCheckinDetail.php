<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OaAppendCheckinDetail extends Model
{
    protected $fillable = ['process_id', 'work_date', 'check_type', 'check_time', 'reason'];

    protected function casts(): array
    {
        return ['work_date' => 'date:Y-m-d'];
    }
}
