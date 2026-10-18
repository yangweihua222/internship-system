<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 教学活动
        Schema::create('teaching_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->text('content')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('location')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('status', 20)->default('published')->comment('published/finished/canceled');
            $table->timestamps();
        });

        Schema::create('teaching_event_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_event_id')->constrained('teaching_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('sign_in_at')->nullable();
            $table->dateTime('sign_out_at')->nullable();
            $table->unsignedInteger('score')->nullable()->comment('活动评分');
            $table->string('comment', 500)->nullable();
            $table->timestamps();
            $table->unique(['teaching_event_id', 'user_id']);
        });

        // 实习报告 / 总结
        Schema::create('internship_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('type', 20)->default('summary')->comment('summary=实习总结 report=出科报告');
            $table->string('title', 200);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->longText('content');
            $table->string('status', 20)->default('draft')->comment('draft/submitted/approved/rejected');
            $table->unsignedInteger('score')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'status']);
        });

        // 通知公告
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('content');
            $table->foreignId('publisher_id')->constrained('users');
            $table->dateTime('published_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
        Schema::dropIfExists('internship_reports');
        Schema::dropIfExists('teaching_event_records');
        Schema::dropIfExists('teaching_events');
    }
};
