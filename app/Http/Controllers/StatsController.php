<?php
// app/Http/Controllers/StatsController.php (continued)

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatsController extends Controller
{
    /**
     * Get all stats for all sites
     */
    public function all()
    {
        try {
            $sites = Site::with('latestStat')->get();
            
            $stats = $sites->map(function ($site) {
                return [
                    'site_id' => $site->id,
                    'site_name' => $site->name,
                    'status' => $site->status,
                    'current' => [
                        'cpu' => $site->latestStat?->cpu ?? 0,
                        'memory' => $site->latestStat?->memory ?? 0,
                        'disk' => $site->latestStat?->disk ?? 0,
                        'requests' => $site->latestStat?->requests ?? 0,
                        'updated_at' => $site->latestStat?->recorded_at?->toISOString()
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch all stats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stats'
            ], 500);
        }
    }

    /**
     * Get system stats
     */
    public function system()
    {
        try {
            // Get system load average
            $load = sys_getloadavg();
            
            // Get memory info
            $memory = $this->getMemoryInfo();
            
            // Get disk info
            $disk = $this->getDiskInfo();
            
            // Get container count
            $totalSites = Site::count();
            $runningSites = Site::where('status', 'running')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'cpu' => [
                        'load' => $load,
                        'usage' => $load[0] * 100,
                        'cores' => $this->getCpuCores()
                    ],
                    'memory' => $memory,
                    'disk' => $disk,
                    'containers' => [
                        'total' => $totalSites * 3, // Approximate (WP + MySQL + Redis)
                        'running' => $runningSites * 3,
                        'sites' => $totalSites,
                        'active_sites' => $runningSites
                    ],
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch system stats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system stats'
            ], 500);
        }
    }

    /**
     * Get container stats
     */
    public function containers()
    {
        try {
            $sites = Site::with('latestStat')->where('status', 'running')->get();
            
            $containerStats = [];
            
            foreach ($sites as $site) {
                $containerStats[] = [
                    'site_id' => $site->id,
                    'site_name' => $site->name,
                    'containers' => [
                        'wordpress' => [
                            'name' => $site->container_id,
                            'status' => $site->status,
                            'cpu' => $site->latestStat?->cpu ?? 0,
                            'memory' => $site->latestStat?->memory ?? 0
                        ],
                        'mysql' => [
                            'name' => $site->container_id . '_db',
                            'status' => $site->status,
                            'cpu' => $site->latestStat?->cpu ?? 0,
                            'memory' => $site->latestStat?->memory ?? 0
                        ],
                        'redis' => [
                            'name' => $site->container_id . '_redis',
                            'status' => $site->status,
                            'cpu' => $site->latestStat?->cpu ?? 0,
                            'memory' => $site->latestStat?->memory ?? 0
                        ]
                    ]
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $containerStats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch container stats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch container stats'
            ], 500);
        }
    }

    /**
     * Clean up old stats
     */
    public function cleanup()
    {
        try {
            // Keep only last 30 days of stats
            $deleted = SiteStat::where('recorded_at', '<', now()->subDays(30))->delete();
            
            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$deleted} old stat records",
                'data' => [
                    'deleted_count' => $deleted,
                    'retention_days' => 30
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cleanup stats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup stats'
            ], 500);
        }
    }

    /**
     * Get memory information
     */
    private function getMemoryInfo()
    {
        try {
            $memoryFile = '/proc/meminfo';
            
            if (file_exists($memoryFile)) {
                $content = file_get_contents($memoryFile);
                
                preg_match('/MemTotal:\s+(\d+)\s+kB/', $content, $total);
                preg_match('/MemFree:\s+(\d+)\s+kB/', $content, $free);
                preg_match('/MemAvailable:\s+(\d+)\s+kB/', $content, $available);
                
                $totalMem = isset($total[1]) ? (int)$total[1] * 1024 : 0;
                $freeMem = isset($free[1]) ? (int)$free[1] * 1024 : 0;
                $availableMem = isset($available[1]) ? (int)$available[1] * 1024 : 0;
                
                return [
                    'total' => $this->formatBytes($totalMem),
                    'free' => $this->formatBytes($freeMem),
                    'available' => $this->formatBytes($availableMem),
                    'used' => $this->formatBytes($totalMem - $freeMem),
                    'percentage' => $totalMem > 0 ? round(($totalMem - $freeMem) / $totalMem * 100, 1) : 0
                ];
            }
            
            return [
                'total' => '0B',
                'free' => '0B',
                'available' => '0B',
                'used' => '0B',
                'percentage' => 0
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get memory info: ' . $e->getMessage());
            
            return [
                'total' => '0B',
                'free' => '0B',
                'available' => '0B',
                'used' => '0B',
                'percentage' => 0
            ];
        }
    }

    /**
     * Get disk information
     */
    private function getDiskInfo()
    {
        try {
            $path = storage_path();
            $total = disk_total_space($path);
            $free = disk_free_space($path);
            $used = $total - $free;
            
            return [
                'total' => $this->formatBytes($total),
                'free' => $this->formatBytes($free),
                'used' => $this->formatBytes($used),
                'percentage' => $total > 0 ? round($used / $total * 100, 1) : 0
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get disk info: ' . $e->getMessage());
            
            return [
                'total' => '0B',
                'free' => '0B',
                'used' => '0B',
                'percentage' => 0
            ];
        }
    }

    /**
     * Get CPU cores
     */
    private function getCpuCores()
    {
        try {
            if (file_exists('/proc/cpuinfo')) {
                $cpuinfo = file_get_contents('/proc/cpuinfo');
                preg_match_all('/^processor/m', $cpuinfo, $matches);
                return count($matches[0]) ?: 1;
            }
            
            return 1;
            
        } catch (\Exception $e) {
            return 1;
        }
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . $units[$pow];
    }
}