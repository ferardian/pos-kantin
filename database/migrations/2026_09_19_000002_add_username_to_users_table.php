<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
        });

        // Populate existing users' username from email or default
        $users = DB::table('users')->get();
        foreach ($users as $u) {
            $baseUsername = !empty($u->email) ? strtolower(explode('@', $u->email)[0]) : 'user' . $u->id;
            DB::table('users')->where('id', $u->id)->update([
                'username' => $baseUsername
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
            $table->string('email')->nullable(false)->change();
        });
    }
};
