<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rotation_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('轮转计划名称');
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('teaching_year_id')->constrained('teaching_years')->cascadeOnDelete();
            $table->string('status', 20)->default('draft')->comment('draft/active/finished');
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('rotation_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rotation_plan_id')->constrained('rotation_plans')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->date('start_date')->comment('入科日期');
            $table->date('end_date')->comment('出科日期');
            $table->string('status', 20)->default('scheduled')->comment('scheduled/in-progress/finished');
            $table->timestamps();
            $table->index(['rotation_plan_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotation_details');
        Schema::dropIfExists('rotation_plans');
    }
};
