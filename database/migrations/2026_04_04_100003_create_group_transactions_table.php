<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('member_id')->nullable(); // null = group-wide
            $table->enum('type', ['deposit', 'withdrawal', 'interest', 'membership_fee']);
            $table->string('posting_type')->default('individual'); // individual | equal_split | custom
            $table->decimal('amount', 15, 2);          // member-level amount
            $table->decimal('balance_before', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->date('transaction_date');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('journal_transaction_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('group_members')->nullOnDelete();
            $table->foreign('journal_transaction_id')->references('id')->on('transactions')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_transactions');
    }
};
