<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** users.last_login_at — stamped on every sign-in; back-filled from the audit log's past logins. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        if (Schema::hasTable('audit_logs')) {
            $last = DB::table('audit_logs')->where('event', 'login')->whereNotNull('user_id')
                ->groupBy('user_id')->selectRaw('user_id, MAX(created_at) as at')->pluck('at', 'user_id');
            foreach ($last as $userId => $at) {
                DB::table('users')->where('id', $userId)->update(['last_login_at' => $at]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
