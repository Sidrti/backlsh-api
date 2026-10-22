<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use Symfony\Component\Process\Process;

class CloneEntireProdDbCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:clone-from-prod
                            {--prod-host=173.212.213.153 : Production MySQL host}
                            {--prod-db=back_backlsh : Production database name}
                            {--prod-user=back_backlsh : Production database user}
                            {--prod-pass=Rammandir@2021 : Production database password}
                            {--prod-port=3306 : Production MySQL port}
                            {--local-host=127.0.0.1 : Local MySQL host}
                            {--local-db=bl : Local database name to restore into}
                            {--local-user=root : Local database user}
                            {--local-pass=root123 : Local database password}
                            {--local-port=3306 : Local MySQL port}
                            {--keep-dump : Keep the downloaded .sql dump file instead of deleting it}
                            {--from-dump= : Restore directly from an existing .sql dump file (e.g. latest or path)}
                            {--seed-sample-feed=1 : Also ensure team activity feed like "Arti gave Siddhant a shoutout 👏"}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clones the entire production database into the local MySQL database for development';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        $this->info("=======================================================");
        $this->info("  🚀 CLONE ENTIRE PRODUCTION DATABASE TO LOCAL");
        $this->info("=======================================================");

        $prodHost = $this->option('prod-host');
        $prodDb   = $this->option('prod-db');
        $prodUser = $this->option('prod-user');
        $prodPass = $this->option('prod-pass');
        $prodPort = $this->option('prod-port');

        $localHost = $this->option('local-host');
        $localDb   = $this->option('local-db');
        $localUser = $this->option('local-user');
        $localPass = $this->option('local-pass');
        $localPort = $this->option('local-port');

        $dumpDir = database_path('dumps');
        if (!is_dir($dumpDir)) {
            @mkdir($dumpDir, 0755, true);
        }

        $fromDumpOption = $this->option('from-dump');
        $useExistingDump = false;
        $dumpFile = null;

        if ($fromDumpOption !== null && $fromDumpOption !== false) {
            $useExistingDump = true;
            if ($fromDumpOption === '' || $fromDumpOption === 'latest' || $fromDumpOption === '1') {
                $dumps = glob($dumpDir . '/*.sql');
                if (!empty($dumps)) {
                    usort($dumps, fn($a, $b) => filemtime($b) - filemtime($a));
                    $dumpFile = $dumps[0];
                }
            } else {
                $dumpFile = $fromDumpOption;
            }

            if (!$dumpFile || !file_exists($dumpFile)) {
                $this->error("❌ Could not find local dump file: " . ($dumpFile ?: 'None found'));
                return 1;
            }
            $this->info("ℹ️ Using existing local dump file: {$dumpFile}");
        }

        $this->line("Source: " . ($useExistingDump ? $dumpFile : "{$prodUser}@{$prodHost}:{$prodPort}/{$prodDb}"));
        $this->line("Target: {$localUser}@{$localHost}:{$localPort}/{$localDb}");

        // 1. Locate binaries
        $mysqldumpBin = $this->findBinary('mysqldump');
        $mysqlBin     = $this->findBinary('mysql');

        if (!$mysqlBin) {
            $this->error("❌ Could not find mysql CLI tool on your system.");
            return 1;
        }

        // 2. Test connections
        if (!$useExistingDump) {
            $this->line("\n📡 Step 1/5: Testing connections...");
            try {
                $prodPdo = new PDO("mysql:host={$prodHost};port={$prodPort};dbname={$prodDb}", $prodUser, $prodPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 6,
                ]);
                $this->info("  ✅ Connected to Production database.");
            } catch (\Exception $e) {
                $this->error("  ❌ Failed to connect to Production MySQL on {$prodHost}:{$prodPort}: " . $e->getMessage());
                
                $currentIp = trim(@file_get_contents('https://ifconfig.me') ?: 'Unknown');
                $this->line("\n💡 Why this happens:");
                $this->line("   1. Remote MySQL port 3306 is firewalled by your hosting provider (Contabo / cPanel / CyberPanel).");
                $this->line("      Your current public IP is: {$currentIp}");
                $this->line("      Whitelist '{$currentIp}' on port 3306 in cPanel 'Remote MySQL' or server firewall.");

                $dumps = glob($dumpDir . '/*.sql');
                if (!empty($dumps)) {
                    $this->line("\n   2. You already have a downloaded production dump file locally!");
                    $this->line("      Run with --from-dump to restore it immediately without remote connection:");
                    $this->line("      php artisan db:clone-from-prod --from-dump=latest");
                }
                return 1;
            }
        }

        try {
            $localPdo = new PDO("mysql:host={$localHost};port={$localPort}", $localUser, $localPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $this->info("  ✅ Connected to Local MySQL server.");
        } catch (\Exception $e) {
            $this->error("  ❌ Failed to connect to Local MySQL: " . $e->getMessage());
            return 1;
        }

        // Increase local max_allowed_packet
        try {
            $localPdo->exec("SET GLOBAL max_allowed_packet = 536870912;");
        } catch (\Exception $e) {
            // Ignore if lack super privileges
        }

        // Ensure local database exists
        $localPdo->exec("CREATE DATABASE IF NOT EXISTS `{$localDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

        // 3. Dump from production (if not using existing dump)
        if (!$useExistingDump) {
            $dumpFile = $dumpDir . '/prod_dump_' . date('Ymd_His') . '.sql';

            $this->line("\n⏳ Step 2/5: Dumping entire database from Production (with compression)...");
            $dumpCmd = sprintf(
                '%s -h %s -P %s -u %s %s --ssl=0 --single-transaction --quick --compress --default-character-set=utf8mb4 --max_allowed_packet=512M %s > %s',
                escapeshellarg($mysqldumpBin),
                escapeshellarg($prodHost),
                escapeshellarg($prodPort),
                escapeshellarg($prodUser),
                $prodPass ? '-p' . escapeshellarg($prodPass) : '',
                escapeshellarg($prodDb),
                escapeshellarg($dumpFile)
            );

            $dumpProcess = Process::fromShellCommandline($dumpCmd);
            $dumpProcess->setTimeout(1800); // 30 minutes max
            $dumpProcess->run();

            if (!$dumpProcess->isSuccessful() || !file_exists($dumpFile) || filesize($dumpFile) === 0) {
                $this->error("  ❌ Failed to dump production database: " . $dumpProcess->getErrorOutput());
                if (file_exists($dumpFile)) {
                    @unlink($dumpFile);
                }
                return 1;
            }

            $dumpSizeMB = round(filesize($dumpFile) / 1024 / 1024, 2);
            $this->info("  ✅ Dump completed successfully: {$dumpSizeMB} MB.");
        } else {
            $dumpSizeMB = round(filesize($dumpFile) / 1024 / 1024, 2);
            $this->info("  ℹ️ Restoring from local dump: {$dumpFile} ({$dumpSizeMB} MB).");
        }

        // 4. Import into local database
        $this->line("\n⏳ Step 3/5: Importing dump into local database '{$localDb}'...");
        $importCmd = sprintf(
            '%s -h %s -P %s -u %s %s --max_allowed_packet=512M %s < %s',
            escapeshellarg($mysqlBin),
            escapeshellarg($localHost),
            escapeshellarg($localPort),
            escapeshellarg($localUser),
            $localPass ? '-p' . escapeshellarg($localPass) : '',
            escapeshellarg($localDb),
            escapeshellarg($dumpFile)
        );

        $importProcess = Process::fromShellCommandline($importCmd);
        $importProcess->setTimeout(1800);
        $importProcess->run();

        if (!$importProcess->isSuccessful()) {
            $this->error("  ❌ Failed to import dump into local database: " . $importProcess->getErrorOutput());
            return 1;
        }
        // 5. Point Laravel DB connection to local target database
        config([
            'database.connections.mysql.host' => $localHost,
            'database.connections.mysql.port' => $localPort,
            'database.connections.mysql.database' => $localDb,
            'database.connections.mysql.username' => $localUser,
            'database.connections.mysql.password' => $localPass,
        ]);
        \Illuminate\Support\Facades\DB::purge('mysql');

        // Run migrations
        $this->line("\n🔧 Step 4/5: Running migrations to verify latest schema...");
        try {
            $this->call('migrate', ['--force' => true]);
        } catch (\Exception $e) {
            $this->warn("  ⚠️ Migration check note: " . $e->getMessage());
        }

        // 6. Ensure Kudos activity feed & manager prompts exist
        if ($this->option('seed-sample-feed')) {
            $this->line("\n✨ Step 5/5: Ensuring team recognition feed & manager prompts exist...");
            try {
                $this->call('recognition:sync-from-prod', [
                    '--local-db' => $localDb,
                    '--local-user' => $localUser,
                    '--local-pass' => $localPass,
                    '--local-host' => $localHost,
                    '--local-port' => $localPort,
                    '--seed-sample-feed' => 1,
                    '--seed-prompts' => 1,
                ]);
            } catch (\Exception $e) {
                // Ignore if remote connection is currently firewalled
            }
        }

        // Clean up dump file only if newly downloaded and not requested to keep
        if (!$useExistingDump && !$this->option('keep-dump')) {
            @unlink($dumpFile);
            $this->line("  ℹ️ Temporary dump file removed.");
        } else {
            $this->line("  ℹ️ Dump file preserved at: {$dumpFile}");
        }

        $elapsedSeconds = round(microtime(true) - $startTime, 1);
        $this->info("\n=======================================================");
        $this->info("  🎉 PRODUCTION DATABASE CLONED SUCCESSFULLY!");
        $this->info("  Target DB: {$localDb} | Duration: {$elapsedSeconds}s");
        $this->info("=======================================================");

        return 0;
    }

    /**
     * Search for binary across standard PATH and directories
     */
    protected function findBinary(string $binary): ?string
    {
        $commonPaths = [
            '/opt/homebrew/bin/' . $binary,
            '/usr/local/bin/' . $binary,
            '/usr/bin/' . $binary,
        ];

        foreach ($commonPaths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        $process = Process::fromShellCommandline('which ' . escapeshellarg($binary));
        $process->run();
        if ($process->isSuccessful()) {
            $path = trim($process->getOutput());
            if ($path && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}
