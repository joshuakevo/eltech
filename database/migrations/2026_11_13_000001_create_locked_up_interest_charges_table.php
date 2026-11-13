<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interest charged by hand on Locked-Up loans (rate 0, no schedule, never touched by Run Loans).
 * Each charge adds to loans.outstanding_interest with no journal (interest is income 4001 when recovered)
 * and shows as a dated "Interest charged" line on the loan page and statement. Undo deletes the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('locked_up_interest_charges')) {
            return;
        }
        Schema::create('locked_up_interest_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->date('charge_date');                    // date the interest is charged (statement date)
            $table->date('from_date')->nullable();          // period covered (when calculated by days)
            $table->unsignedInteger('days')->nullable();
            $table->decimal('principal', 18, 2)->nullable(); // principal the rate was applied to
            $table->decimal('rate', 9, 4)->nullable();       // % p.a.
            $table->decimal('amount', 18, 2);
            $table->string('notes', 255)->nullable();
            $table->decimal('interest_before', 18, 2);       // outstanding_interest before the charge
            $table->json('repayment_ids');                   // recoveries at the time (undo guard)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['loan_id', 'charge_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locked_up_interest_charges');
    }
};
