<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationQuestion extends Model
{
    protected $fillable = ['template_id', 'content', 'max_score', 'sort'];
}
