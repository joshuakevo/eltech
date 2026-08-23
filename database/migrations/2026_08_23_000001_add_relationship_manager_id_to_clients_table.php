<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clients', 'relationship_manager_id')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('relationship_manager_id')->nullable()->after('segment_id');
            $table->foreign('relationship_manager_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['relationship_manager_id']);
            $table->dropColumn('relationship_manager_id');
        });
    }
};
