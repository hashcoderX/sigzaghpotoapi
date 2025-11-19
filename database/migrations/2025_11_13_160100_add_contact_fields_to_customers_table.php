<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('whatsapp')->nullable()->after('phone');
            $table->string('nic_or_dl')->nullable()->after('whatsapp');
            $table->text('address')->nullable()->after('company');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['whatsapp', 'nic_or_dl', 'address']);
        });
    }
};
