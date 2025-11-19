<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('booking_id')->constrained('invoices')->nullOnDelete();
            $table->foreignId('job_card_id')->nullable()->after('invoice_id')->constrained('job_cards')->nullOnDelete();
            $table->string('reference')->nullable()->after('method');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_card_id');
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('reference');
        });
    }
};
