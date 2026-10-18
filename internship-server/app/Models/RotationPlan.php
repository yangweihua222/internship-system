<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RotationPlan extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FINISHED = 'finished';

    protected $fillable = ['name', 'start_date', 'end_date', 'status', 'remark'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    /** 参与本轮轮转的学生 */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'rotation_plan_student', 'rotation_plan_id', 'student_id')
            ->withTimestamps();
    }

    /** 分段科室安排 */
    public function details(): HasMany
    {
        return $this->hasMany(RotationDetail::class)->orderBy('start_date');
    }
}
