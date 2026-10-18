<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('科室名称');
            $table->foreignId('parent_id')->nullable()->comment('上级科室')->constrained('departments')->nullOnDelete();
            $table->unsignedInteger('sort')->default(0)->comment('排序，越小越靠前');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
