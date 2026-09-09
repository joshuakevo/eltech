<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            'view dashboard',

            // Clients
            'view clients', 'create clients', 'edit clients', 'delete clients',

            // Accounts / Chart of Accounts
            'view accounts', 'create accounts', 'edit accounts',

            // Transactions (journal entries)
            'view transactions', 'create transactions', 'reverse transactions',

            // Loan Products
            'view loan-products', 'create loan-products', 'edit loan-products',

            // Loans
            'view loans', 'create loans', 'disburse loans', 'repay loans', 'run loans',

            // Savings Products
            'view savings-products', 'create savings-products', 'edit savings-products',

            // Savings Accounts
            'view savings', 'create savings', 'deposit savings', 'withdraw savings', 'transfer savings', 'overdraw savings',

            // FD Products
            'view fd-products', 'create fd-products', 'edit fd-products',

            // Fixed Deposits
            'view fixed-deposits', 'create fixed-deposits', 'mature fixed-deposits',

            // Teller
            'use teller',

            // Reports
            'view reports', 'view loan reports', 'view savings reports', 'send statements', 'send sms',

            // Mobile Money
            'approve mobile money',

            // Employees
            'view employees', 'create employees', 'edit employees',

            // Payroll
            'view payroll', 'create payroll', 'process payroll', 'delete payroll',

            // Member Shares
            'manage shares',

            // Administration
            'manage branches', 'manage client segments', 'manage users', 'manage settings', 'manage backup', 'close accounts',

            // Groups
            'view groups', 'manage groups',

            // CRM
            'view crm', 'manage crm',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // ── ROLES ──────────────────────────────────────────────

        // Super Admin — all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin, Cashier, Staff — all start with every permission. Use the Roles &
        // Permissions screen to uncheck what a given role should NOT have; there is
        // no more curated default set baked in here.
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        $cashier = Role::firstOrCreate(['name' => 'cashier']);
        $cashier->syncPermissions(Permission::all());

        $staff = Role::firstOrCreate(['name' => 'staff']);
        $staff->syncPermissions(Permission::all());

        // Portal roles (client-facing) are gated by `role:` middleware on separate
        // client-portal / group-portal routes, not by `permission:` middleware on the
        // admin panel routes. They must NOT be granted admin permissions — doing so
        // would let a client/group-portal login pass the permission checks on the
        // main back-office routes (e.g. /accounts, /transactions, /users).
        Role::firstOrCreate(['name' => 'group_leader']);
        Role::firstOrCreate(['name' => 'group_member']);
        Role::firstOrCreate(['name' => 'client']);
    }
}
