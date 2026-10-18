<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 病历
        Schema::create('patient_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('patient_name', 50)->comment('患者姓名');
            $table->string('admission_no', 50)->nullable()->comment('住院号');
            $table->string('title', 200)->comment('病历标题，如入院记录');
            $table->longText('content');
            $table->string('status', 20)->default('draft')->comment('draft/submitted/approved/rejected');
            $table->unsignedInteger('score')->nullable();
            $table->text('comment')->nullable()->comment('老师评语');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status']);
        });

        // 病程记录
        Schema::create('medical_progresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_case_id')->nullable()->constrained('patient_cases')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('title', 200);
            $table->longText('content');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('score')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status']);
        });

        // 操作/手术记录
        Schema::create('operation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('type', 20)->default('operation')->comment('operation=临床操作 surgery=手术');
            $table->string('project_name', 100)->comment('操作/手术项目名称');
            $table->string('patient_name', 50)->nullable();
            $table->date('operation_date');
            $table->string('role', 20)->default('助手')->comment('主刀/助手/观摩');
            $table->longText('content');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('score')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_records');
        Schema::dropIfExists('medical_progresses');
        Schema::dropIfExists('patient_cases');
    }
};
