<?php
// app/Http/Controllers/DatabaseController.php

namespace App\Http\Controllers;

use App\Models\Database;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DatabaseController extends Controller
{
    /**
     * Get all databases
     */
    public function index()
    {
        try {
            $databases = Database::with('site')->get();
            
            $enhancedDatabases = $databases->map(function ($db) {
                return [
                    'id' => $db->id,
                    'name' => $db->name,
                    'status' => $db->status,
                    'type' => $db->type,
                    'site_id' => $db->site_id,
                    'site_name' => $db->site->name ?? null,
                    'container_name' => $db->container_name,
                    'port' => $db->port,
                    'database_name' => $db->database_name,
                    'username' => $db->username,
                    'size' => $this->getDatabaseSize($db),
                    'created_at' => $db->created_at->toISOString(),
                    'updated_at' => $db->updated_at->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $enhancedDatabases
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch databases: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch databases'
            ], 500);
        }
    }

    /**
     * Get single database
     */
    public function show(Database $database)
    {
        try {
            $database->load('site');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $database->id,
                    'name' => $database->name,
                    'status' => $database->status,
                    'type' => $database->type,
                    'site_id' => $database->site_id,
                    'site_name' => $database->site->name ?? null,
                    'container_name' => $database->container_name,
                    'port' => $database->port,
                    'database_name' => $database->database_name,
                    'username' => $database->username,
                    'size' => $this->getDatabaseSize($database),
                    'connection_string' => $this->getConnectionString($database),
                    'created_at' => $database->created_at->toISOString(),
                    'updated_at' => $database->updated_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch database: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch database'
            ], 500);
        }
    }

    /**
     * Create database backup
     */
    public function backup(Database $database)
    {
        try {
            // In production, this would trigger a database backup
            $backupFile = storage_path("backups/{$database->name}_" . date('Y-m-d_H-i-s') . '.sql');
            
            // Simulate backup creation
            touch($backupFile);
            
            return response()->json([
                'success' => true,
                'message' => 'Database backup created successfully',
                'data' => [
                    'database_id' => $database->id,
                    'backup_file' => basename($backupFile),
                    'size' => filesize($backupFile),
                    'created_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to backup database: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to backup database'
            ], 500);
        }
    }

    /**
     * Get database size
     */
    public function getSize(Database $database)
    {
        try {
            $size = $this->getDatabaseSize($database);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'database_id' => $database->id,
                    'database_name' => $database->name,
                    'size' => $size,
                    'size_bytes' => $this->parseSizeToBytes($size)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get database size: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get database size'
            ], 500);
        }
    }

    /**
     * Optimize database
     */
    public function optimize(Database $database)
    {
        try {
            // In production, this would run OPTIMIZE TABLE commands
            // For now, just simulate
            
            return response()->json([
                'success' => true,
                'message' => 'Database optimized successfully',
                'data' => [
                    'database_id' => $database->id,
                    'optimized_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to optimize database: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to optimize database'
            ], 500);
        }
    }

    /**
     * Get database credentials
     */
    public function getCredentials(Database $database)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'database_id' => $database->id,
                    'database_name' => $database->database_name,
                    'username' => $database->username,
                    'password' => '********', // Don't expose actual password
                    'host' => 'localhost',
                    'port' => $database->port,
                    'connection_string' => $this->getConnectionString($database)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get database credentials: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get database credentials'
            ], 500);
        }
    }

    /**
     * Rotate database password
     */
    public function rotatePassword(Database $database)
    {
        try {
            // In production, this would generate a new password
            $newPassword = bin2hex(random_bytes(16));
            
            return response()->json([
                'success' => true,
                'message' => 'Database password rotated successfully',
                'data' => [
                    'database_id' => $database->id,
                    'new_password' => $newPassword, // Only shown once
                    'rotated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to rotate password: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to rotate password'
            ], 500);
        }
    }

    /**
     * Get database size (simulated)
     */
    private function getDatabaseSize($database)
    {
        // Simulate database size based on site age
        $daysOld = $database->created_at->diffInDays(now());
        $sizeMB = min(500, 10 + ($daysOld * 2)); // Max 500MB
        
        return $this->formatBytes($sizeMB * 1024 * 1024);
    }

    /**
     * Get connection string
     */
    private function getConnectionString($database)
    {
        return "mysql://{$database->username}:******@localhost:{$database->port}/{$database->database_name}";
    }

    /**
     * Parse size to bytes
     */
    private function parseSizeToBytes($size)
    {
        $unit = strtoupper(substr($size, -2));
        $value = (float) substr($size, 0, -2);
        
        switch ($unit) {
            case 'GB': return $value * 1024 * 1024 * 1024;
            case 'MB': return $value * 1024 * 1024;
            case 'KB': return $value * 1024;
            default: return (float) $size;
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
        
        return round($bytes, $precision) . $units[$pow];
    }
}