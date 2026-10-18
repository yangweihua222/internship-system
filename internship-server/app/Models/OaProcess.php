<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OaProcess extends Model
{
    public const TYPE_LEAVE = 'leave';
    public const TYPE_LEAVE_CANCEL = 'leave_cancel';
    public const TYPE_SHIFT_CHANGE = 'shift_change';
    public const TYPE_APPEND_CHECKIN = 'append_checkin';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = ['type', 'applicant_id', 'title', 'status', 'current_node'];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(OaProcessNode::class, 'process_id')->orderBy('node_order');
    }

    public function leaveDetail(): HasOne
    {
        return $this->hasOne(OaLeaveDetail::class, 'process_id');
    }

    public function leaveCancelDetail(): HasOne
    {
        return $this->hasOne(OaLeaveCancelDetail::class, 'process_id');
    }

    public function shiftChangeDetail(): HasOne
    {
        return $this->hasOne(OaShiftChangeDetail::class, 'process_id');
    }

    public function appendCheckinDetail(): HasOne
    {
        return $this->hasOne(OaAppendCheckinDetail::class, 'process_id');
    }
}
