<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('班次名称，如白班/夜班');
            $table->time('start_time')->comment('上班时间');
            $table->time('end_time')->comment('下班时间');
            $table->unsignedInteger('late_threshold_minutes')->default(10)->comment('迟到宽限分钟');
            $table->timestamps();
        });

        Schema::create('attendance_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('考勤组名称');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable()->comment('打卡围栏中心纬度');
            $table->decimal('lng', 10, 7)->nullable()->comment('打卡围栏中心经度');
            $table->unsignedInteger('radius_m')->default(300)->comment('围栏半径（米）');
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_group_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('attendance_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
        });

        Schema::create('attendance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('attendance_groups')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'work_date']);
        });

        Schema::create('checkin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            $table->string('check_type', 10)->comment('in=上班打卡 out=下班打卡');
            $table->dateTime('checked_at')->comment('打卡时间');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('distance_m')->nullable()->comment('距围栏中心距离（米）');
            $table->string('result', 20)->comment('normal/outside');
            $table->string('source', 20)->default('self')->comment('self=自主打卡 oa=补卡审批');
            $table->timestamps();
        });

        Schema::create('checkin_day_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->string('status', 20)->default('unknown')->comment('normal/late/early/absent/leave/unknown');
            $table->unsignedInteger('late_minutes')->default(0);
            $table->string('remark')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_day_records');
        Schema::dropIfExists('checkin_logs');
        Schema::dropIfExists('attendance_schedules');
        Schema::dropIfExists('attendance_group_users');
        Schema::dropIfExists('attendance_groups');
        Schema::dropIfExists('shifts');
    }
};
