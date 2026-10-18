<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 轮转模型重构：去掉班级/教学年，轮转 = 一批学生 + 总周期 + 周期内分段科室安排
 */
return new class extends Migration
{
    public function up(): void
    {
        // rotation_plans：去掉班级/教学年，加总周期
        Schema::table('rotation_plans', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropForeign(['teaching_year_id']);
            $table->dropColumn(['class_id', 'teaching_year_id']);
            $table->date('start_date')->nullable()->after('name')->comment('轮转开始日期');
            $table->date('end_date')->nullable()->after('start_date')->comment('轮转结束日期');
        });

        // 轮转参与学生
        Schema::create('rotation_plan_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rotation_plan_id')->constrained('rotation_plans')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['rotation_plan_id', 'student_id']);
        });

        // users 去掉班级
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropColumn('class_id');
        });

        // 废弃表
        Schema::dropIfExists('teaching_weeks');
        Schema::dropIfExists('teaching_years');
        Schema::dropIfExists('classes');
    }

    public function down(): void
    {
        Schema::dropIfExists('rotation_plan_student');
        Schema::table('rotation_plans', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
