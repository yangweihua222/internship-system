<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationAnswer extends Model
{
    protected $fillable = ['evaluation_id', 'question_id', 'score'];
}
