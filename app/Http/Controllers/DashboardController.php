<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Database;
use App\Models\RedisInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Get dashboard summary
     */
    public function index()
    {
        try {
            $sites = Site::all();
            $databases = Database::all();
            $redis = RedisInstance::all();
            
            $runningSites = $sites->where('status', 'running')->count();
            $totalSites = $sites->count();
            
            $activeDatabases = $databases->where('status', 'connected')->count();
            $totalDatabases = $databases->count();
            
            $activeRedis = $redis->where('status', 'cached')->count();
            $totalRedis = $redis->count();
            
            // Calculate container counts
            $totalContainers = ($totalSites * 2) + $totalRedis; // WordPress + MySQL + Redis
            $runningContainers = ($runningSites * 2) + $activeRedis;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_sites' => $totalSites,
                    'running_sites' => $runningSites,
                    'stopped_sites' => $totalSites - $runningSites,
                    'total_databases' => $totalDatabases,
                    'active_databases' => $activeDatabases,
                    'total_redis' => $totalRedis,
                    'active_redis' => $activeRedis,
                    'total_containers' => $totalContainers,
                    'running_containers' => $runningContainers,
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get dashboard: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get dashboard data'
            ], 500);
        }
    }

    /**
     * Get detailed summary
     */
    public function summary()
    {
        try {
            $sites = Site::with(['database', 'redis'])->get();
            
            $summary = [
                'sites' => [
                    'total' => $sites->count(),
                    'running' => $sites->where('status', 'running')->count(),
                    'stopped' => $sites->where('status', 'stopped')->count(),
                    'pending' => $sites->where('status', 'pending')->count()
                ],
                'resources' => [
                    'total_cpu' => $this->getTotalCpuUsage($sites),
                    'total_memory' => $this->getTotalMemoryUsage($sites),
                    'total_disk' => $this->getTotalDiskUsage()
                ],
                'recent_activity' => $this->getRecentActivity($sites),
                'system' => $this->getSystemInfo()
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get summary: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get summary'
            ], 500);
        }
    }

    /**
     * Get system health
     */
    public function health()
    {
        try {
            $health = [
                'status' => 'healthy',
                'docker' => $this->checkDocker(),
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'disk' => $this->checkDiskSpace(),
                'memory' => $this->checkMemory(),
                'last_check' => now()->toISOString()
            ];

            // Determine overall status
            if (in_array(false, array_column($health, 'status'))) {
                $health['status'] = 'degraded';
            }

            return response()->json([
                'success' => true,
                'data' => $health
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check health: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check health',
                'data' => [
                    'status' => 'unknown',
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get system info
     */
    public function systemInfo()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'php_version' => phpversion(),
                    'laravel_version' => app()->version(),
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'database' => config('database.default'),
                    'environment' => app()->environment(),
                    'timezone' => config('app.timezone'),
                    'debug_mode' => config('app.debug'),
                    'cache_driver' => config('cache.default'),
                    'session_driver' => config('session.driver')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get system info: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get system info'
            ], 500);
        }
    }

    /**
     * Get system requirements
     */
    public function requirements()
    {
        try {
            $requirements = [
                'php' => [
                    'version' => phpversion(),
                    'required' => '8.1',
                    'meets' => version_compare(phpversion(), '8.1', '>=')
                ],
                'extensions' => [
                    'json' => extension_loaded('json'),
                    'mysql' => extension_loaded('pdo_mysql'),
                    'redis' => extension_loaded('redis'),
                    'curl' => extension_loaded('curl'),
                    'zip' => extension_loaded('zip')
                ],
                'docker' => [
                    'installed' => $this->isDockerInstalled(),
                    'version' => $this->getDockerVersion()
                ],
                'disk_space' => [
                    'free' => $this->formatBytes(disk_free_space(storage_path())),
                    'total' => $this->formatBytes(disk_total_space(storage_path()))
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $requirements
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get requirements: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get requirements'
            ], 500);
        }
    }

    /**
     * Get total CPU usage
     */
    private function getTotalCpuUsage($sites)
    {
        $total = 0;
        foreach ($sites as $site) {
            $total += $site->latestStat?->cpu ?? 0;
        }
        return round($total, 1) . '%';
    }

    /**
     * Get total memory usage
     */
    private function getTotalMemoryUsage($sites)
    {
        $total = 0;
        foreach ($sites as $site) {
            $total += $site->latestStat?->memory ?? 0;
        }
        return $this->formatBytes($total * 1024 * 1024);
    }

    /**
     * Get total disk usage
     */
    private function getTotalDiskUsage()
    {
        $total = 0;
        $path = storage_path();
        $total = disk_total_space($path) - disk_free_space($path);
        return $this->formatBytes($total);
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity($sites)
    {
        $activity = [];
        
        foreach ($sites->sortByDesc('updated_at')->take(5) as $site) {
            $activity[] = [
                'site_id' => $site->id,
                'site_name' => $site->name,
                'action' => 'status_changed',
                'status' => $site->status,
                'time' => $site->updated_at->diffForHumans(),
                'timestamp' => $site->updated_at->toISOString()
            ];
        }
        
        return $activity;
    }

    /**
     * Get system info
     */
    private function getSystemInfo()
    {
        return [
            'hostname' => gethostname(),
            'os' => php_uname(),
            'uptime' => $this->getSystemUptime()
        ];
    }

    /**
     * Get system uptime
     */
    private function getSystemUptime()
    {
        if (file_exists('/proc/uptime')) {
            $uptime = (float) file_get_contents('/proc/uptime');
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            return "{$days}d {$hours}h";
        }
        return 'Unknown';
    }

    /**
     * Check Docker
     */
    private function checkDocker()
    {
        try {
            exec('docker --version', $output, $returnCode);
            return $returnCode === 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check database
     */
    private function checkDatabase()
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check Redis
     */
    private function checkRedis()
    {
        try {
            if (extension_loaded('redis')) {
                $redis = new \Redis();
                $redis->connect('127.0.0.1', 6379);
                return $redis->ping() === '+PONG';
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check disk space
     */
    private function checkDiskSpace()
    {
        $free = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());
        $percentage = ($total - $free) / $total * 100;
        
        return [
            'healthy' => $percentage < 90,
            'free' => $this->formatBytes($free),
            'used' => $this->formatBytes($total - $free),
            'percentage' => round($percentage, 1) . '%'
        ];
    }

    /**
     * Check memory
     */
    private function checkMemory()
    {
        $memory = $this->getMemoryInfo();
        return [
            'healthy' => $memory['percentage'] < 90,
            'used' => $memory['used'],
            'total' => $memory['total'],
            'percentage' => $memory['percentage'] . '%'
        ];
    }

    /**
     * Check if Docker is installed
     */
    private function isDockerInstalled()
    {
        return $this->checkDocker();
    }

    /**
     * Get Docker version
     */
    private function getDockerVersion()
    {
        try {
            exec('docker --version', $output);
            return $output[0] ?? 'Unknown';
        } catch (\Exception $e) {
            return 'Not installed';
        }
    }

    /**
     * Format bytes
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Get memory info
     */
    private function getMemoryInfo()
    {
        try {
            $memoryFile = '/proc/meminfo';
            
            if (file_exists($memoryFile)) {
                $content = file_get_contents($memoryFile);
                
                preg_match('/MemTotal:\s+(\d+)\s+kB/', $content, $total);
                preg_match('/MemFree:\s+(\d+)\s+kB/', $content, $free);
                
                $totalMem = isset($total[1]) ? (int)$total[1] * 1024 : 0;
                $freeMem = isset($free[1]) ? (int)$free[1] * 1024 : 0;
                
                return [
                    'total' => $this->formatBytes($totalMem),
                    'free' => $this->formatBytes($freeMem),
                    'used' => $this->formatBytes($totalMem - $freeMem),
                    'percentage' => $totalMem > 0 ? round(($totalMem - $freeMem) / $totalMem * 100, 1) : 0
                ];
            }
            
            return [
                'total' => '0 B',
                'free' => '0 B',
                'used' => '0 B',
                'percentage' => 0
            ];
            
        } catch (\Exception $e) {
            return [
                'total' => '0 B',
                'free' => '0 B',
                'used' => '0 B',
                'percentage' => 0
            ];
        }
    }
}