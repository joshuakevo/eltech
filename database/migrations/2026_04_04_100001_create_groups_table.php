<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_number')->unique();
            $table->string('name');
            $table->date('registration_date');
            $table->decimal('membership_fee', 15, 2)->default(0);
            $table->decimal('monthly_interest_rate', 8, 4)->default(0); // e.g. 1.5 = 1.5%
            $table->unsignedBigInteger('gl_account_id')->nullable(); // Group savings liability GL account
            $table->enum('status', ['active', 'inactive', 'dissolved'])->default('active');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('gl_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
