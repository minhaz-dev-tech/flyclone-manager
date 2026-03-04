<?php
namespace App\Jobs;

use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Redis;
use App\Services\DockerService;

class CollectStatsJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(DockerService $docker)
    {
        $sites = Site::all();
        foreach ($sites as $site) {
            $stats = $docker->getStats($site->container_id);
            Redis::set("site:{$site->id}:stats", json_encode($stats));
        }
    }
}