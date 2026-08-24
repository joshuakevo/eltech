<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transaction_lines', 'segment_id')) {
            return;
        }

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('segment_id')->nullable()->after('client_id');
            $table->foreign('segment_id')->references('id')->on('client_segments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropForeign(['segment_id']);
            $table->dropColumn('segment_id');
        });
    }
};
