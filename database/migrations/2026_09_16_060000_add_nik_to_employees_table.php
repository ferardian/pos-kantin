<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employees', 'nik')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('nik')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'nik')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('nik');
            });
        }
    }
};
