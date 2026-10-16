<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Merges a duplicate member record into the main one: every account, loan, deposit, share, journal line
 * tag, note and link moves to the main member unchanged (accounts keep their own numbers and balances,
 * so they stay separate accounts), then the duplicate is set inactive and soft-deleted.
 * No journals are needed — balances don't change, only who owns the records.
 */
class ClientMergeService
{
    /** Tables whose client_id simply moves to the main member. */
    public const TABLES = [
        'savings_accounts', 'loans', 'fixed_deposits', 'member_shares', 'share_transactions',
        'loan_guarantors', 'loan_provision_lines', 'transaction_lines', 'group_members',
        'client_notes', 'client_tasks', 'mobile_money_transactions', 'sms_logs', 'employees', 'users',
    ];

    /** What would move, per table (only tables that exist and have rows). */
    public function preview(Client $source): array
    {
        $counts = [];
        foreach (self::TABLES as $table) {
            if (\Schema::hasTable($table)) {
                $n = DB::table($table)->where('client_id', $source->id)->count();
                if ($n) {
                    $counts[$table] = $n;
                }
            }
        }
        return $counts;
    }

    public function merge(Client $target, Client $source): array
    {
        if ((int) $target->id === (int) $source->id) {
            throw new InvalidArgumentException('Choose a different member to merge.');
        }
        if ($target->isGroup() || $source->isGroup()) {
            throw new InvalidArgumentException('Group clients cannot be merged.');
        }

        return DB::transaction(function () use ($target, $source) {
            $moved = [];
            foreach (self::TABLES as $table) {
                if (!\Schema::hasTable($table)) {
                    continue;
                }
                $ids = DB::table($table)->where('client_id', $source->id)->pluck('id')->all();
                if ($ids) {
                    DB::table($table)->whereIn('id', $ids)->update(['client_id' => $target->id]);
                    $moved[$table] = $ids;
                }
            }

            // Portal links (composite key): give the main member's portal the same users, drop the duplicate's links
            foreach (DB::table('client_portal_users')->where('client_id', $source->id)->pluck('user_id') as $userId) {
                DB::table('client_portal_users')->insertOrIgnore(['user_id' => $userId, 'client_id' => $target->id]);
            }
            DB::table('client_portal_users')->where('client_id', $source->id)->delete();

            // Membership fee paid on the duplicate counts for the member
            if ((float) $source->membership_fee_paid > 0 && (float) $target->membership_fee_paid <= 0) {
                $target->update([
                    'membership_fee_paid'   => $source->membership_fee_paid,
                    'membership_fee_status' => $source->membership_fee_status,
                ]);
            }

            $source->update(['status' => 'inactive']);
            $source->delete();

            $summary = collect($moved)->map(fn ($ids, $t) => $t . ' ' . implode(',', $ids))->implode('; ');
            AuditLog::record('client_merged',
                "Merged {$source->name} ({$source->client_number}, id {$source->id}) into {$target->name} ({$target->client_number}). Moved: " . ($summary ?: 'nothing'),
                'clients');

            return array_map('count', $moved);
        });
    }
}
