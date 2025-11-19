<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'premium_package')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('premium_package')->default(false)->after('active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'premium_package')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('premium_package');
            });
        }
    }
};
