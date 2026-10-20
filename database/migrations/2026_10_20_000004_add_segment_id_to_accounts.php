<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Default segment of a GL account: its lines count under this segment when not tied to a member and not tagged on the line. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('accounts', 'segment_id')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->unsignedBigInteger('segment_id')->nullable()->after('parent_id');
                $table->foreign('segment_id')->references('id')->on('client_segments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['segment_id']);
            $table->dropColumn('segment_id');
        });
    }
};
