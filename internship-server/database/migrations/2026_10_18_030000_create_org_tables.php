<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('班级名称');
            $table->string('grade', 20)->nullable()->comment('年级，如 2024');
            $table->timestamps();
        });

        Schema::create('teaching_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('教学年名称，如 2026-2027 学年');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('teaching_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_year_id')->constrained('teaching_years')->cascadeOnDelete();
            $table->unsignedInteger('week_number')->comment('第几周');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->unique(['teaching_year_id', 'week_number']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('class_id')->nullable()->after('department_id')->constrained('classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropColumn('class_id');
        });
        Schema::dropIfExists('teaching_weeks');
        Schema::dropIfExists('teaching_years');
        Schema::dropIfExists('classes');
    }
};
