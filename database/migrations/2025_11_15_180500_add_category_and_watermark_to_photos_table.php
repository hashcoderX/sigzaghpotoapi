<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('category')->after('title');
            $table->string('watermark_path')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['category', 'watermark_path']);
        });
    }
};
