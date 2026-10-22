<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use PDO;

class SyncRecognitionFromProdCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recognition:sync-from-prod
                            {--prod-host=173.212.213.153 : Production MySQL host}
                            {--prod-db=back_backlsh : Production database name}
                            {--prod-user=back_backlsh : Production database user}
                            {--prod-pass=Rammandir@2021 : Production database password}
                            {--prod-port=3306 : Production MySQL port}
                            {--local-host=127.0.0.1 : Local MySQL host}
                            {--local-db=bl : Local database name}
                            {--local-user=root : Local database user}
                            {--local-pass=root123 : Local database password}
                            {--local-port=3306 : Local MySQL port}
                            {--seed-sample-feed=1 : Also ensure team activity feed like "Arti gave Siddhant a shoutout 👏"}
                            {--seed-prompts=1 : Also seed sample manager prompts for local UI testing}
                            {--truncate : Truncate local recognition tables before copying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs recognitions data from Production database to Local database for development';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info("=======================================================");
        $this->info("  🔄 SYNC RECOGNITION DATA: PROD -> LOCAL DATABASE");
        $this->info("=======================================================");

        $prodHost = $this->option('prod-host');
        $prodDb = $this->option('prod-db');
        $prodUser = $this->option('prod-user');
        $prodPass = $this->option('prod-pass');
        $prodPort = $this->option('prod-port');

        $localHost = $this->option('local-host');
        $localDb = $this->option('local-db');
        $localUser = $this->option('local-user');
        $localPass = $this->option('local-pass');
        $localPort = $this->option('local-port');

        $this->line("Connecting to Prod: {$prodUser}@{$prodHost}:{$prodPort}/{$prodDb}...");
        try {
            $prodPdo = new PDO(
                "mysql:host={$prodHost};port={$prodPort};dbname={$prodDb};charset=utf8mb4",
                $prodUser,
                $prodPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            $this->info("  ✅ Connected to Production database.");
        } catch (\Exception $e) {
            $this->error("  ❌ Failed to connect to Production: " . $e->getMessage());
            return 1;
        }

        $this->line("Connecting to Local: {$localUser}@{$localHost}:{$localPort}/{$localDb}...");
        try {
            $localPdo = new PDO(
                "mysql:host={$localHost};port={$localPort};dbname={$localDb};charset=utf8mb4",
                $localUser,
                $localPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            $this->info("  ✅ Connected to Local database.");
        } catch (\Exception $e) {
            $this->error("  ❌ Failed to connect to Local DB: " . $e->getMessage());
            return 1;
        }

        // Optional truncate
        if ($this->option('truncate')) {
            $this->warn("Truncating local recognitions and recognition_prompts tables...");
            $localPdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $localPdo->exec("TRUNCATE TABLE recognitions;");
            if ($this->tableExists($localPdo, 'recognition_prompts')) {
                $localPdo->exec("TRUNCATE TABLE recognition_prompts;");
            }
            $localPdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            $this->info("  ✅ Local recognition tables truncated.");
        }

        // 1. Copy recognitions
        $this->line("Fetching recognitions from Production...");
        $prodRecognitionsStmt = $prodPdo->query("SELECT * FROM recognitions ORDER BY id ASC");
        $prodRecognitions = $prodRecognitionsStmt->fetchAll();
        $this->info("  Found " . count($prodRecognitions) . " recognitions in Production.");

        $copiedCount = 0;
        $insertStmt = $localPdo->prepare("
            INSERT INTO recognitions (id, sender_id, recipient_id, preset_type, message, created_at, updated_at)
            VALUES (:id, :sender_id, :recipient_id, :preset_type, :message, :created_at, :updated_at)
            ON DUPLICATE KEY UPDATE
                sender_id = VALUES(sender_id),
                recipient_id = VALUES(recipient_id),
                preset_type = VALUES(preset_type),
                message = VALUES(message),
                updated_at = VALUES(updated_at)
        ");

        foreach ($prodRecognitions as $row) {
            // Ensure sender and recipient exist in local users
            $this->ensureUserExists($prodPdo, $localPdo, $row['sender_id']);
            $this->ensureUserExists($prodPdo, $localPdo, $row['recipient_id']);

            $insertStmt->execute([
                ':id' => $row['id'],
                ':sender_id' => $row['sender_id'],
                ':recipient_id' => $row['recipient_id'],
                ':preset_type' => $row['preset_type'] ?? 'CUSTOM',
                ':message' => $row['message'],
                ':created_at' => $row['created_at'],
                ':updated_at' => $row['updated_at'],
            ]);
            $copiedCount++;
        }
        $this->info("  ✅ Copied/updated {$copiedCount} recognition records into local DB.");

        // 2. Copy recognition_prompts if exists in prod
        if ($this->tableExists($prodPdo, 'recognition_prompts') && $this->tableExists($localPdo, 'recognition_prompts')) {
            $this->line("Fetching recognition_prompts from Production...");
            $prodPromptsStmt = $prodPdo->query("SELECT * FROM recognition_prompts ORDER BY id ASC");
            $prodPrompts = $prodPromptsStmt->fetchAll();
            $promptsCount = 0;

            $insertPromptStmt = $localPdo->prepare("
                INSERT INTO recognition_prompts (id, admin_id, candidate_user_id, trigger_key, title, message, suggested_preset, status, sent_at, created_at, updated_at)
                VALUES (:id, :admin_id, :candidate_user_id, :trigger_key, :title, :message, :suggested_preset, :status, :sent_at, :created_at, :updated_at)
                ON DUPLICATE KEY UPDATE
                    admin_id = VALUES(admin_id),
                    candidate_user_id = VALUES(candidate_user_id),
                    title = VALUES(title),
                    message = VALUES(message),
                    status = VALUES(status),
                    updated_at = VALUES(updated_at)
            ");

            foreach ($prodPrompts as $row) {
                $this->ensureUserExists($prodPdo, $localPdo, $row['admin_id']);
                $this->ensureUserExists($prodPdo, $localPdo, $row['candidate_user_id']);

                $insertPromptStmt->execute([
                    ':id' => $row['id'],
                    ':admin_id' => $row['admin_id'],
                    ':candidate_user_id' => $row['candidate_user_id'],
                    ':trigger_key' => $row['trigger_key'],
                    ':title' => $row['title'],
                    ':message' => $row['message'],
                    ':suggested_preset' => $row['suggested_preset'] ?? 'GREAT_WORK',
                    ':status' => $row['status'] ?? 'PENDING',
                    ':sent_at' => $row['sent_at'],
                    ':created_at' => $row['created_at'],
                    ':updated_at' => $row['updated_at'],
                ]);
                $promptsCount++;
            }
            $this->info("  ✅ Copied/updated {$promptsCount} recognition prompts into local DB.");
        }

        // 3. Seed sample activity feed (e.g., "Arti gave Siddhant a shoutout 👏")
        if ($this->option('seed-sample-feed')) {
            $this->seedTeamActivityFeed($localPdo);
        }

        // 4. Seed sample prompts for local UI testing
        if ($this->option('seed-prompts') && $this->tableExists($localPdo, 'recognition_prompts')) {
            $this->seedSamplePrompts($localPdo);
        }

        $this->info("=======================================================");
        $this->info("  🎉 RECOGNITION DATA SYNC COMPLETED SUCCESSFULLY!");
        $this->info("  Your local activity feed and leaderboard are now live.");
        $this->info("=======================================================");

        return 0;
    }

    /**
     * Ensure a user exists in local database by pulling from prod if missing
     */
    protected function ensureUserExists(PDO $prodPdo, PDO $localPdo, int $userId): void
    {
        $stmt = $localPdo->prepare("SELECT id FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        if ($stmt->fetch()) {
            return;
        }

        // Pull user from prod
        $prodUserStmt = $prodPdo->prepare("SELECT * FROM users WHERE id = :id");
        $prodUserStmt->execute([':id' => $userId]);
        $userRow = $prodUserStmt->fetch();

        if (!$userRow) {
            return;
        }

        $cols = array_keys($userRow);
        $placeholders = array_map(fn($c) => ":{$c}", $cols);

        $sql = "INSERT IGNORE INTO users (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $insertUser = $localPdo->prepare($sql);
        $params = [];
        foreach ($userRow as $key => $val) {
            $params[":{$key}"] = $val;
        }
        $insertUser->execute($params);
        $this->line("  ℹ️ Imported missing user ID {$userId} ({$userRow['name']}) from production.");
    }

    /**
     * Check if a table exists
     */
    protected function tableExists(PDO $pdo, string $tableName): bool
    {
        try {
            $res = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
            return $res->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Seed realistic team activity feed items including "Arti gave Siddhant a shoutout 👏"
     */
    protected function seedTeamActivityFeed(PDO $localPdo): void
    {
        $this->line("Checking team activity feed items...");

        // Find user IDs for Pallav, Arti, Siddhant, Harshit, Sumith
        $userMap = [];
        $usersStmt = $localPdo->query("SELECT id, name FROM users WHERE id IN (38, 39, 68, 69, 70)");
        foreach ($usersStmt->fetchAll() as $u) {
            $userMap[$u['id']] = $u['name'];
        }

        $now = Carbon::now();

        // Feed items to ensure
        $sampleShoutouts = [
            [
                'sender_id' => 70, // Arti
                'recipient_id' => 68, // Siddhant
                'preset_type' => 'GREAT_WORK',
                'message' => 'Crushed the sprint deliverables ahead of schedule! Amazing work Siddhant 🎉',
                'created_at' => (clone $now)->subHours(2)->toDateTimeString(),
            ],
            [
                'sender_id' => 39, // Harshit Mishra
                'recipient_id' => 70, // Arti
                'preset_type' => 'ABOVE_AND_BEYOND',
                'message' => 'Huge thanks for helping out with the client demo prep yesterday! 🚀',
                'created_at' => (clone $now)->subHours(6)->toDateTimeString(),
            ],
            [
                'sender_id' => 68, // Siddhant
                'recipient_id' => 69, // Sumith
                'preset_type' => 'CONSISTENT_EFFORT',
                'message' => 'Always dependable and steady all week long! Keep it up 💪',
                'created_at' => (clone $now)->subDays(1)->toDateTimeString(),
            ],
            [
                'sender_id' => 38, // Pallav
                'recipient_id' => 70, // Arti
                'preset_type' => 'GREAT_WORK',
                'message' => 'Outstanding presentations with the client team this week! 👏',
                'created_at' => (clone $now)->subDays(2)->toDateTimeString(),
            ],
            [
                'sender_id' => 69, // Sumith
                'recipient_id' => 39, // Harshit Mishra
                'preset_type' => 'GREAT_WORK',
                'message' => 'Great turnaround on resolving the API issues quickly! 🌟',
                'created_at' => (clone $now)->subDays(3)->toDateTimeString(),
            ],
        ];

        $checkStmt = $localPdo->prepare("
            SELECT id FROM recognitions
            WHERE sender_id = :sender_id AND recipient_id = :recipient_id AND message = :message
        ");

        $insertStmt = $localPdo->prepare("
            INSERT INTO recognitions (sender_id, recipient_id, preset_type, message, created_at, updated_at)
            VALUES (:sender_id, :recipient_id, :preset_type, :message, :created_at, :updated_at)
        ");

        $seeded = 0;
        foreach ($sampleShoutouts as $shoutout) {
            $checkStmt->execute([
                ':sender_id' => $shoutout['sender_id'],
                ':recipient_id' => $shoutout['recipient_id'],
                ':message' => $shoutout['message'],
            ]);

            if (!$checkStmt->fetch()) {
                $insertStmt->execute([
                    ':sender_id' => $shoutout['sender_id'],
                    ':recipient_id' => $shoutout['recipient_id'],
                    ':preset_type' => $shoutout['preset_type'],
                    ':message' => $shoutout['message'],
                    ':created_at' => $shoutout['created_at'],
                    ':updated_at' => $shoutout['created_at'],
                ]);
                $seeded++;
            }
        }

        if ($seeded > 0) {
            $this->info("  ✅ Seeded {$seeded} realistic shoutouts in team activity feed (including 'Arti gave Siddhant a shoutout 👏').");
        } else {
            $this->info("  ℹ️ Activity feed items already present.");
        }
    }

    /**
     * Seed pending prompts for admin so prompt banner is testable locally
     */
    protected function seedSamplePrompts(PDO $localPdo): void
    {
        $this->line("Checking recognition prompts for local UI testing...");

        $now = Carbon::now();
        $samplePrompts = [
            [
                'admin_id' => 38,
                'candidate_user_id' => 68, // Siddhant
                'trigger_key' => 'VOL_PERSONAL_HIGH_WEEKLY',
                'title' => '👏 Weekly Personal High: Siddhant',
                'message' => 'Siddhant logged 44.5h last week — their highest weekly hours in the past 4 weeks! A quick shoutout goes a long way.',
                'suggested_preset' => 'CONSISTENT_EFFORT',
                'status' => 'PENDING',
                'created_at' => (clone $now)->subHours(4)->toDateTimeString(),
            ],
            [
                'admin_id' => 38,
                'candidate_user_id' => 70, // Arti
                'trigger_key' => 'CON_MONTH_HEALTHY_STEADY',
                'title' => '🎯 Steady & Reliable: Arti',
                'message' => 'Arti maintained consistent healthy hours for 4 consecutive weeks. Acknowledge their great dependability!',
                'suggested_preset' => 'GREAT_WORK',
                'status' => 'PENDING',
                'created_at' => (clone $now)->subHours(12)->toDateTimeString(),
            ],
        ];

        $checkStmt = $localPdo->prepare("
            SELECT id FROM recognition_prompts
            WHERE admin_id = :admin_id AND candidate_user_id = :candidate_user_id AND trigger_key = :trigger_key AND status = 'PENDING'
        ");

        $insertStmt = $localPdo->prepare("
            INSERT INTO recognition_prompts (admin_id, candidate_user_id, trigger_key, title, message, suggested_preset, status, created_at, updated_at)
            VALUES (:admin_id, :candidate_user_id, :trigger_key, :title, :message, :suggested_preset, :status, :created_at, :updated_at)
        ");

        $promptsSeeded = 0;
        foreach ($samplePrompts as $prompt) {
            $checkStmt->execute([
                ':admin_id' => $prompt['admin_id'],
                ':candidate_user_id' => $prompt['candidate_user_id'],
                ':trigger_key' => $prompt['trigger_key'],
            ]);

            if (!$checkStmt->fetch()) {
                $insertStmt->execute([
                    ':admin_id' => $prompt['admin_id'],
                    ':candidate_user_id' => $prompt['candidate_user_id'],
                    ':trigger_key' => $prompt['trigger_key'],
                    ':title' => $prompt['title'],
                    ':message' => $prompt['message'],
                    ':suggested_preset' => $prompt['suggested_preset'],
                    ':status' => $prompt['status'],
                    ':created_at' => $prompt['created_at'],
                    ':updated_at' => $prompt['created_at'],
                ]);
                $promptsSeeded++;
            }
        }

        if ($promptsSeeded > 0) {
            $this->info("  ✅ Seeded {$promptsSeeded} sample recognition prompts for Pallav (ID: 38).");
        } else {
            $this->info("  ℹ️ Sample recognition prompts already present.");
        }
    }
}
