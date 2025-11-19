<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('discount', 10, 2)->nullable()->after('due_at');
            $table->decimal('advance_payment', 10, 2)->nullable()->after('discount');
            $table->decimal('due_amount', 10, 2)->nullable()->after('advance_payment');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['discount','advance_payment','due_amount']);
        });
    }
};
