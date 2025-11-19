<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->decimal('advance_payment', 10, 2)->nullable()->after('confirmed_amount');
        });
    }

    public function down(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropColumn('advance_payment');
        });
    }
};
