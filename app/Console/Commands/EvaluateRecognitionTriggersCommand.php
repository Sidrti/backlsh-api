<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RecognitionTriggerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EvaluateRecognitionTriggersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recognition:evaluate-triggers {--force : Force evaluation ignoring day-of-week checks} {--admin_id= : Run for specific company admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate time-tracking data against 17 recognition triggers and dispatch recognition reminders to managers';

    /**
     * Execute the console command.
     *
     * @param RecognitionTriggerService $service
     * @return int
     */
    public function handle(RecognitionTriggerService $service): int
    {
        $this->info("EvaluateRecognitionTriggers: Starting recognition triggers evaluation...");
        Log::info("EvaluateRecognitionTriggers: Starting command execution.");

        $targetAdminId = $this->option('admin_id');

        if ($targetAdminId) {
            $admins = User::where('id', $targetAdminId)->get();
        } else {
            // All company owners / top-level admins (parent_user_id == 0 or role == ADMIN)
            $admins = User::where('parent_user_id', 0)
                ->where('role', 'ADMIN')
                ->whereIn('status', [1, '1', 'ACTIVE'])
                ->get();
        }

        $this->info("Found {$admins->count()} organization admins to evaluate.");

        $totalDispatched = 0;

        foreach ($admins as $admin) {
            $this->line("Evaluating triggers for company: {$admin->name} (ID: {$admin->id})...");
            try {
                $result = $service->evaluateAllTriggersForTeam($admin->id);
                $dispatched = $result['dispatched_count'] ?? 0;
                $totalDispatched += $dispatched;
                $this->info("Company {$admin->name}: {$dispatched} recognition prompts generated.");
            } catch (\Exception $e) {
                Log::error("Error evaluating triggers for admin {$admin->id}: " . $e->getMessage());
                $this->error("Failed for company {$admin->id}: " . $e->getMessage());
            }
        }

        $this->info("EvaluateRecognitionTriggers finished. Total prompts dispatched: {$totalDispatched}.");
        Log::info("EvaluateRecognitionTriggers finished. Total prompts dispatched: {$totalDispatched}.");

        return 0;
    }
}
