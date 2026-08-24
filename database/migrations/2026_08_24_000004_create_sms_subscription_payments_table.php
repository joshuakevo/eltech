<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->decimal('amount', 15, 2);
            $table->uuid('reference')->unique();
            $table->string('provider_reference')->nullable();
            $table->enum('status', ['pending', 'processing', 'successful', 'failed', 'cancelled'])->default('pending');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_subscription_payments');
    }
};
