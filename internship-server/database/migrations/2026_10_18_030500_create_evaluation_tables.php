<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 评价模板
        Schema::create('evaluation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('target', 20)->comment('student=评学生 teacher=评老师 discharge=出科评价');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('evaluation_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
            $table->string('content', 300)->comment('题目');
            $table->unsignedInteger('max_score')->default(10);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // 评价实例：一次评价一条
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete()->comment('评价人');
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete()->comment('被评价人');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('period', 50)->nullable()->comment('评价周期，如 2026-10 / 第3轮');
            $table->unsignedInteger('total_score');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->index(['target_user_id', 'period']);
        });

        Schema::create('evaluation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('evaluation_questions')->cascadeOnDelete();
            $table->unsignedInteger('score');
            $table->timestamps();
            $table->unique(['evaluation_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_answers');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('evaluation_questions');
        Schema::dropIfExists('evaluation_templates');
    }
};
