<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 64)->unique()->after('id');
            $table->string('type', 20)->default('student')->after('name'); // admin / teacher / student
            $table->string('phone', 20)->nullable()->after('type');
            $table->foreignId('department_id')->nullable()->after('phone')->constrained('departments')->nullOnDelete();
            $table->boolean('status')->default(true)->after('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['username', 'type', 'phone', 'department_id', 'status']);
        });
    }
};
