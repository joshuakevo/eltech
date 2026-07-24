<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_products', function (Blueprint $table) {
            $table->enum('interest_method', ['flat', 'tiered'])->default('flat')->after('interest_rate');
        });
    }

    public function down(): void
    {
        Schema::table('savings_products', function (Blueprint $table) {
            $table->dropColumn('interest_method');
        });
    }
};
