<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceGroup extends Model
{
    protected $fillable = ['name', 'department_id', 'lat', 'lng', 'radius_m', 'remark'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'attendance_group_users', 'group_id', 'user_id')
            ->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(AttendanceSchedule::class, 'group_id');
    }

    /** 是否配置了打卡围栏 */
    public function hasFence(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
