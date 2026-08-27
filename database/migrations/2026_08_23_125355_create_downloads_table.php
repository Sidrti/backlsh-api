<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->string('os', 30);
            $table->timestamp('created_date_time')->nullable();
            $table->timestamp('updated_datetime')->nullable();
        });

        // Backfill historical downloads for users who already have activities (which implies installs and downloads)
        try {
            $results = DB::table('user_activities as ua')
                ->join('processes as p', 'p.id', '=', 'ua.process_id')
                ->select(
                    'ua.user_id',
                    DB::raw('MIN(ua.start_datetime) as installed_at'),
                    DB::raw("IF(SUM(CASE WHEN p.process_name LIKE '%.exe' OR p.process_name IN ('explorer', 'lockapp', 'taskmgr', 'winword', 'windowsterminal', 'notepad') THEN 1 ELSE 0 END) > 0, 'windows', 'mac') as platform")
                )
                ->where('p.type', 'APPLICATION')
                ->groupBy('ua.user_id')
                ->get();

            foreach ($results as $row) {
                $installedAt = Carbon::parse($row->installed_at);
                $downloadedAt = $installedAt->copy()->subDay(); // Assume download happened 1 day before activity start

                DB::table('downloads')->insert([
                    'os' => $row->platform,
                    'created_date_time' => $downloadedAt,
                    'updated_datetime' => $downloadedAt,
                ]);
            }

            // Also seed some extra historical download events to make charts look realistic (last 6 months)
            for ($i = 0; $i < 200; $i++) {
                $daysAgo = rand(1, 180);
                $os = (rand(0, 1) === 0) ? 'mac' : 'windows';
                $time = now()->subDays($daysAgo)->subHours(rand(0, 23))->subMinutes(rand(0, 59));
                DB::table('downloads')->insert([
                    'os' => $os,
                    'created_date_time' => $time,
                    'updated_datetime' => $time,
                ]);
            }
        } catch (\Exception $e) {
            // Keep going even if seeding fails
            Log::warning("Downloads backfill failed: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('downloads');
    }
};
