<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** CRM → Send Email: one row per email sent (or failed), and the "send emails" permission (roles that can send SMS). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_logs')) {
            Schema::create('email_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
                $table->string('email');
                $table->string('subject');
                $table->text('body');
                $table->string('attachment_name')->nullable();
                $table->string('category', 30)->nullable();
                $table->string('status', 10);           // sent | failed
                $table->text('error')->nullable();
                $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'created_at']);
            });
        }
        if (Schema::hasTable('permissions')) {
            $guard = config('auth.defaults.guard', 'web');
            DB::table('permissions')->insertOrIgnore(['name' => 'send emails', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
            $perm = DB::table('permissions')->where('name', 'send emails')->value('id');
            $sms  = DB::table('permissions')->where('name', 'send sms')->value('id');
            $roles = DB::table('role_has_permissions')->where('permission_id', $sms)->pluck('role_id')
                ->merge(DB::table('roles')->whereIn('name', ['super_admin', 'admin'])->pluck('id'))->unique();
            foreach ($roles as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $perm, 'role_id' => $roleId]);
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
        DB::table('permissions')->where('name', 'send emails')->delete();
    }
};
