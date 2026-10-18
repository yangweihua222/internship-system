<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oa_processes', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->comment('leave/leave_cancel/shift_change/append_checkin');
            $table->foreignId('applicant_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('status', 20)->default('pending')->comment('pending/approved/rejected/canceled');
            $table->unsignedInteger('current_node')->default(1)->comment('当前待审批节点序号');
            $table->timestamps();
            $table->index(['applicant_id', 'status']);
        });

        Schema::create('oa_process_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('oa_processes')->cascadeOnDelete();
            $table->unsignedInteger('node_order');
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->comment('pending/approved/rejected/skipped');
            $table->string('comment', 500)->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->timestamps();
            $table->unique(['process_id', 'node_order']);
        });

        Schema::create('oa_leave_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('oa_processes')->cascadeOnDelete();
            $table->string('leave_type', 20)->comment('事假/病假/其他');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->timestamps();
        });

        Schema::create('oa_leave_cancel_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('oa_processes')->cascadeOnDelete();
            $table->foreignId('leave_process_id')->constrained('oa_processes')->comment('要撤销的原请假单');
            $table->text('reason');
            $table->timestamps();
        });

        Schema::create('oa_shift_change_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('oa_processes')->cascadeOnDelete();
            $table->foreignId('my_schedule_id')->constrained('duty_schedules')->comment('我的值班');
            $table->foreignId('target_schedule_id')->constrained('duty_schedules')->comment('要换到的值班');
            $table->text('reason');
            $table->timestamps();
        });

        Schema::create('oa_append_checkin_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('oa_processes')->cascadeOnDelete();
            $table->date('work_date');
            $table->string('check_type', 10)->comment('in/out');
            $table->time('check_time');
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_append_checkin_details');
        Schema::dropIfExists('oa_shift_change_details');
        Schema::dropIfExists('oa_leave_cancel_details');
        Schema::dropIfExists('oa_leave_details');
        Schema::dropIfExists('oa_process_nodes');
        Schema::dropIfExists('oa_processes');
    }
};
