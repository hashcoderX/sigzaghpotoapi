<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country', 100)->nullable()->after('role');
            $table->text('address')->nullable()->after('country');
            $table->string('currency', 3)->nullable()->after('address');
            $table->string('membership_type', 30)->nullable()->after('currency');
            $table->string('subscription_plan', 20)->nullable()->after('membership_type'); // monthly|yearly
            $table->boolean('auto_renew')->default(false)->after('subscription_plan');
            $table->string('card_last4', 4)->nullable()->after('auto_renew');
            $table->string('payment_provider', 50)->nullable()->after('card_last4');
            $table->string('payment_customer_id', 100)->nullable()->after('payment_provider');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'country',
                'address',
                'currency',
                'membership_type',
                'subscription_plan',
                'auto_renew',
                'card_last4',
                'payment_provider',
                'payment_customer_id',
            ]);
        });
    }
};
