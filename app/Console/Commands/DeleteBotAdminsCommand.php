<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteBotAdminsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:delete-bot-admins
                            {--dry-run : Only list matching bot admin accounts without deleting}
                            {--force : Force delete without interactive confirmation}
                            {--connection= : Database connection name (defaults to default connection)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finds and deletes bot admin users who have 0 sub-users, unverified emails, and 0 recorded activities';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $connection = $this->option('connection') ?: config('database.default');
        $db = DB::connection($connection);

        $this->info("=======================================================");
        $this->info("  🧹 BOT ADMIN CLEANUP TOOL");
        $this->info("=======================================================");
        $this->line("Database Connection: {$connection} (" . config("database.connections.{$connection}.database") . ")");

        // 1. Query matching bot admin accounts
        $botUsers = $db->table('users as u')
            ->select('u.id', 'u.name', 'u.email', 'u.role', 'u.is_verified', 'u.created_at')
            ->where(function ($q) {
                $q->where('u.role', 'ADMIN')
                  ->orWhere('u.parent_user_id', 0);
            })
            ->where(function ($q) {
                $q->where('u.is_verified', 0)
                  ->orWhereNull('u.is_verified');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('users as sub')
                  ->whereColumn('sub.parent_user_id', 'u.id');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('user_activities as ua')
                  ->whereColumn('ua.user_id', 'u.id');
            })
            ->orderBy('u.id', 'asc')
            ->get();

        $count = $botUsers->count();

        if ($count === 0) {
            $this->info("🎉 No bot admin accounts found matching the criteria!");
            return 0;
        }

        $this->warn("⚠️ Found {$count} bot admin account(s) matching all 3 criteria:");
        $this->line("  1. Have 0 sub-users added under them");
        $this->line("  2. Email is unverified (is_verified = 0)");
        $this->line("  3. Have 0 recorded activities / app usage");

        // Display sample table
        $displayRows = $botUsers->take(20)->map(fn($u) => [
            'ID' => $u->id,
            'Name' => $u->name,
            'Email' => $u->email,
            'Created At' => $u->created_at,
        ])->toArray();

        $this->table(['ID', 'Name', 'Email', 'Created At'], $displayRows);

        if ($count > 20) {
            $this->line("... and " . ($count - 20) . " more matching accounts.");
        }

        // If dry-run, stop here
        if ($this->option('dry-run')) {
            $this->info("\nℹ️ Dry-run mode enabled. No accounts were deleted.");
            $this->line("Run without --dry-run (or with --force) to perform the deletion.");
            return 0;
        }

        // Confirm deletion
        if (!$this->option('force')) {
            if (!$this->confirm("Are you sure you want to permanently delete these {$count} bot admin users?", false)) {
                $this->warn("Operation cancelled by user.");
                return 0;
            }
        }

        // Execute deletion inside transaction
        $botUserIds = $botUsers->pluck('id')->toArray();

        $this->line("\n⏳ Deleting {$count} bot users and their orphan auth tokens...");

        $deletedCount = 0;
        $db->transaction(function () use ($db, $botUserIds, &$deletedCount) {
            // Delete related personal_access_tokens
            $db->table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\User')
                ->whereIn('tokenable_id', $botUserIds)
                ->delete();

            // Delete notifications if any
            $db->table('notifications')
                ->whereIn('user_id', $botUserIds)
                ->orWhereIn('triggered_by', $botUserIds)
                ->delete();

            // Delete users
            $deletedCount = $db->table('users')
                ->whereIn('id', $botUserIds)
                ->delete();
        });

        $this->info("=======================================================");
        $this->info("  ✅ SUCCESS: Deleted {$deletedCount} bot admin users.");
        $this->info("=======================================================");

        return 0;
    }
}
