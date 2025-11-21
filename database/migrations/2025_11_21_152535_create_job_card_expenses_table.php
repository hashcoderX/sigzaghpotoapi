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
        Schema::create('job_card_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_card_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // e.g., 'equipment_rental', 'travel', 'supplies', 'subcontractor', etc.
            $table->decimal('amount', 10, 2);
            $table->string('description');
            $table->dateTime('expense_date');
            $table->string('vendor')->nullable(); // who provided the service/goods
            $table->string('receipt_path')->nullable(); // path to uploaded receipt
            $table->json('metadata')->nullable(); // additional event-specific data
            $table->timestamps();

            $table->index(['user_id', 'job_card_id']);
            $table->index(['event_type']);
            $table->index(['expense_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_card_expenses');
    }
};
