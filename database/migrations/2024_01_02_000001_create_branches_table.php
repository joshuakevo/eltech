<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('manager_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add branch_id to key tables
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('client_id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('client_id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
        Schema::table('fixed_deposits', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('client_id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn($t) => $t->dropForeign(['branch_id']));
        Schema::table('fixed_deposits', fn($t) => $t->dropForeign(['branch_id']));
        Schema::table('savings_accounts', fn($t) => $t->dropForeign(['branch_id']));
        Schema::table('loans', fn($t) => $t->dropForeign(['branch_id']));
        Schema::table('clients', fn($t) => $t->dropForeign(['branch_id']));
        Schema::dropIfExists('branches');
    }
};
