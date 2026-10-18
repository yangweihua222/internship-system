<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationRecord extends Model
{
    protected $fillable = [
        'student_id', 'department_id', 'type', 'project_name', 'patient_name',
        'operation_date', 'role', 'content',
        'status', 'score', 'comment', 'reviewer_id', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'operation_date' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
