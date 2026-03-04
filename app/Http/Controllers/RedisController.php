<?php
// app/Http/Controllers/RedisController.php

namespace App\Http\Controllers;

use App\Models\RedisInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RedisController extends Controller
{
    /**
     * Get all Redis instances
     */
    public function index()
    {
        try {
            $redisInstances = RedisInstance::with('site')->get();
            
            $enhancedRedis = $redisInstances->map(function ($redis) {
                return [
                    'id' => $redis->id,
                    'name' => $redis->name,
                    'status' => $redis->status,
                    'type' => $redis->type,
                    'site_id' => $redis->site_id,
                    'site_name' => $redis->site->name ?? null,
                    'container_name' => $redis->container_name,
                    'port' => $redis->port,
                    'stats' => $this->getRedisStats($redis),
                    'created_at' => $redis->created_at->toISOString(),
                    'updated_at' => $redis->updated_at->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $enhancedRedis
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch Redis instances: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch Redis instances'
            ], 500);
        }
    }

    /**
     * Get single Redis instance
     */
    public function show(RedisInstance $redis)
    {
        try {
            $redis->load('site');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $redis->id,
                    'name' => $redis->name,
                    'status' => $redis->status,
                    'type' => $redis->type,
                    'site_id' => $redis->site_id,
                    'site_name' => $redis->site->name ?? null,
                    'container_name' => $redis->container_name,
                    'port' => $redis->port,
                    'stats' => $this->getRedisStats($redis),
                    'info' => $this->getRedisInfo($redis),
                    'created_at' => $redis->created_at->toISOString(),
                    'updated_at' => $redis->updated_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch Redis instance: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch Redis instance'
            ], 500);
        }
    }

    /**
     * Flush Redis cache
     */
    public function flush(RedisInstance $redis)
    {
        try {
            // In production, this would run FLUSHALL command
            
            return response()->json([
                'success' => true,
                'message' => 'Redis cache flushed successfully',
                'data' => [
                    'redis_id' => $redis->id,
                    'flushed_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to flush Redis: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to flush Redis'
            ], 500);
        }
    }

    /**
     * Get Redis info
     */
    public function info(RedisInstance $redis)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'redis_id' => $redis->id,
                    'info' => $this->getRedisInfo($redis)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get Redis info: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get Redis info'
            ], 500);
        }
    }

    /**
     * Get Redis stats
     */
    public function stats(RedisInstance $redis)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'redis_id' => $redis->id,
                    'stats' => $this->getRedisStats($redis)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get Redis stats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get Redis stats'
            ], 500);
        }
    }

    /**
     * Get Redis stats (simulated)
     */
    private function getRedisStats($redis)
    {
        return [
            'connected_clients' => rand(1, 10),
            'used_memory' => $this->formatBytes(rand(10, 100) * 1024 * 1024),
            'used_memory_rss' => $this->formatBytes(rand(15, 120) * 1024 * 1024),
            'used_memory_peak' => $this->formatBytes(rand(50, 200) * 1024 * 1024),
            'total_connections_received' => rand(100, 1000),
            'total_commands_processed' => rand(1000, 10000),
            'keyspace_hits' => rand(500, 5000),
            'keyspace_misses' => rand(50, 500),
            'hit_rate' => rand(80, 99) . '%'
        ];
    }

    /**
     * Get Redis info (simulated)
     */
    private function getRedisInfo($redis)
    {
        return [
            'version' => '7.2.4',
            'uptime_in_seconds' => rand(3600, 86400 * 30),
            'uptime_in_days' => rand(1, 30),
            'redis_mode' => 'standalone',
            'os' => 'Linux 5.15.0',
            'arch_bits' => 64,
            'tcp_port' => $redis->port,
            'keys_count' => rand(100, 1000),
            'expired_keys' => rand(10, 100),
            'evicted_keys' => rand(0, 10),
            'maxmemory' => '1GB',
            'maxmemory_policy' => 'allkeys-lru'
        ];
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
        
        return round($bytes, $precision) . $units[$pow];
    }
}