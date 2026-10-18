<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OaLeaveCancelDetail extends Model
{
    protected $fillable = ['process_id', 'leave_process_id', 'reason'];
}
