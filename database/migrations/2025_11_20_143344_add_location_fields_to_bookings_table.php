<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('wedding_shoot_location')->nullable();
            $table->string('preshoot_location')->nullable();
            $table->string('homecoming_location')->nullable();
            $table->string('function_location')->nullable();
            $table->string('event_covering_location')->nullable();
            $table->string('custom_plan_location')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['wedding_shoot_location', 'preshoot_location', 'homecoming_location', 'function_location', 'event_covering_location', 'custom_plan_location']);
        });
    }
};
