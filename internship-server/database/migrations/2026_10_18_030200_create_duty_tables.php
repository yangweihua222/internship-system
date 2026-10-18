<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->date('duty_date');
            $table->string('shift_label', 30)->default('白班')->comment('值班时段标签，如白班/夜班');
            $table->string('type', 10)->comment('teacher/student，冗余存方便查询');
            $table->string('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'duty_date', 'shift_label']);
            $table->index(['duty_date', 'department_id']);
        });

        Schema::create('duty_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duty_schedule_id')->nullable()->constrained('duty_schedules')->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->date('duty_date');
            $table->string('title', 100);
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('duty_log_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duty_log_id')->constrained('duty_logs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['duty_log_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_log_viewers');
        Schema::dropIfExists('duty_logs');
        Schema::dropIfExists('duty_schedules');
    }
};
