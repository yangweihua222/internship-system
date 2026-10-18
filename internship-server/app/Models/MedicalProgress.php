<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalProgress extends Model
{
    protected $table = 'medical_progresses'; // progress 不可数，Laravel 默认复数化会猜错

    protected $fillable = [
        'student_id', 'patient_case_id', 'department_id', 'title', 'content',
        'status', 'score', 'comment', 'reviewer_id', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'patient_case_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
