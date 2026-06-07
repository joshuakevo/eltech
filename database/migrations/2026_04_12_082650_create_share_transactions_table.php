<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_id')->constrained('member_shares')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['payment', 'revaluation', 'liquidation']);
            $table->decimal('amount', 15, 2)->default(0);       // amount paid / revaluation delta / payout
            $table->decimal('old_value', 15, 2)->nullable();     // for revaluation: value before
            $table->decimal('new_value', 15, 2)->nullable();     // for revaluation: value after
            $table->date('transaction_date');
            $table->string('reference')->nullable();
            $table->string('payment_method')->nullable();        // cash / savings
            $table->text('notes')->nullable();
            $table->foreignId('journal_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_transactions');
    }
};
