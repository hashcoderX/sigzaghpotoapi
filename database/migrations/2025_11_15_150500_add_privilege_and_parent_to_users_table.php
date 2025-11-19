<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('privilege', 30)->nullable()->after('role');
            $table->unsignedBigInteger('parent_user_id')->nullable()->after('privilege');
            $table->foreign('parent_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_user_id']);
            $table->dropColumn(['privilege','parent_user_id']);
        });
    }
};
