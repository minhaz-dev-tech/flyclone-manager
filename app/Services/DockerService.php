<?php
// app/Services/DockerService.php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DockerService
{
    /**
     * Create WordPress container with proper configuration
     */
    public function createWordPress($name, $port, $domain, $ssl = false, $mysqlContainer = null)
    {
        try {
            $siteName = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
            $timestamp = now()->timestamp;
            $containerName = $siteName . '_wp_' . $timestamp;
            
            // Use provided MySQL container or generate one
            if (!$mysqlContainer) {
                $mysqlContainer = $siteName . '_db_' . $timestamp;
            }
            
            // Database credentials (must match MySQL)
            $dbName = 'wordpress_' . $siteName;
            $dbUser = 'wp_' . $siteName;
            $dbPassword = 'password'; // Simple password for demo
            
            // Create network if it doesn't exist
            $this->createNetworkIfNotExists('wpnetwork_' . $siteName);
            
            Log::info("Creating WordPress container: {$containerName}");
            Log::info("Linking to MySQL: {$mysqlContainer}");
            
            // Simple docker run command without complex escaping issues
            $command = "docker run -d " .
                "--name {$containerName} " .
                "--network wpnetwork_{$siteName} " .
                "-p {$port}:80 " .
                "-e WORDPRESS_DB_HOST={$mysqlContainer}:3306 " .
                "-e WORDPRESS_DB_USER={$dbUser} " .
                "-e WORDPRESS_DB_PASSWORD={$dbPassword} " .
                "-e WORDPRESS_DB_NAME={$dbName} " .
                "-e WORDPRESS_DB_CHARSET=utf8mb4 " .
                "-e WORDPRESS_TABLE_PREFIX=wp_ " .
                "--restart unless-stopped " .
                "wordpress:latest 2>&1";
            
            Log::debug('Docker command: ' . $command);
            
            $output = shell_exec($command);
            
            if (strpos($output, 'Error') !== false) {
                throw new \Exception('Docker error: ' . $output);
            }
            
            // Wait for container to be ready
            sleep(5);
            
            return [
                'success' => true,
                'name' => $containerName,
                'mysql_container' => $mysqlContainer,
                'db_name' => $dbName,
                'db_user' => $dbUser,
                'db_password' => $dbPassword,
                'output' => $output
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create WordPress container: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create MySQL container with proper configuration
     */
    public function createMySQL($name, $port)
    {
        try {
            $siteName = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
            $timestamp = now()->timestamp;
            $containerName = $siteName . '_db_' . $timestamp;
            $mysqlPort = $port + 1000;
            
            // Simple credentials
            $dbName = 'wordpress_' . $siteName;
            $dbUser = 'wp_' . $siteName;
            $dbPassword = 'password';
            $rootPassword = 'root';
            
            // Create network
            $this->createNetworkIfNotExists('wpnetwork_' . $siteName);
            
            // Remove any existing container
            $this->removeContainer($containerName);
            
            Log::info("Creating MySQL container: {$containerName}");
            
            // Simple docker run command
            $command = "docker run -d " .
                "--name {$containerName} " .
                "--network wpnetwork_{$siteName} " .
                "-p {$mysqlPort}:3306 " .
                "-e MYSQL_ROOT_PASSWORD={$rootPassword} " .
                "-e MYSQL_DATABASE={$dbName} " .
                "-e MYSQL_USER={$dbUser} " .
                "-e MYSQL_PASSWORD={$dbPassword} " .
                "--restart unless-stopped " .
                "mysql:8.0 " .
                "--character-set-server=utf8mb4 " .
                "--collation-server=utf8mb4_unicode_ci 2>&1";
            
            Log::debug('Docker command: ' . $command);
            
            $output = shell_exec($command);
            
            if (strpos($output, 'Error') !== false) {
                throw new \Exception('Docker error: ' . $output);
            }
            
            Log::info("MySQL container created, waiting for initialization...");
            
            // Wait for MySQL to be ready
            $ready = $this->waitForMySQL($containerName, 60);
            
            if (!$ready) {
                // Check logs even if not ready
                $logs = $this->getLogs($containerName, 50);
                Log::warning("MySQL logs: " . $logs);
            }
            
            return [
                'success' => true,
                'name' => $containerName,
                'port' => $mysqlPort,
                'database' => $dbName,
                'username' => $dbUser,
                'password' => $dbPassword,
                'output' => $output
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create MySQL container: ' . $e->getMessage());
            
            if (isset($containerName)) {
                $this->removeContainer($containerName, true);
            }
            
            throw $e;
        }
    }

    /**
     * Wait for MySQL to be ready
     */
    private function waitForMySQL($containerName, $maxAttempts = 60)
    {
        Log::info("Waiting for MySQL container {$containerName} to be ready...");
        
        for ($i = 1; $i <= $maxAttempts; $i++) {
            // Check container status
            $status = $this->getContainerStatus($containerName);
            
            if ($status !== 'running') {
                if ($i % 10 == 0) {
                    Log::warning("MySQL container status: {$status}, waiting...");
                }
                sleep(1);
                continue;
            }
            
            // Try mysqladmin ping
            $testCommand = "docker exec {$containerName} mysqladmin ping -h localhost --silent 2>&1";
            $result = shell_exec($testCommand);
            
            if (strpos($result, 'mysqld is alive') !== false) {
                Log::info("MySQL is ready after {$i} seconds");
                return true;
            }
            
            // Show progress every 10 seconds
            if ($i % 10 == 0) {
                Log::info("Still waiting for MySQL... ({$i}/{$maxAttempts})");
            }
            
            sleep(1);
        }
        
        Log::warning("MySQL may not be fully ready, but continuing...");
        return false; // Return false but don't throw exception
    }

    /**
     * Create Redis container
     */
    public function createRedis($name, $port)
    {
        try {
            $siteName = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
            $timestamp = now()->timestamp;
            $containerName = $siteName . '_redis_' . $timestamp;
            $redisPort = $port + 2000;
            
            $this->createNetworkIfNotExists('wpnetwork_' . $siteName);
            
            $command = "docker run -d " .
                "--name {$containerName} " .
                "--network wpnetwork_{$siteName} " .
                "-p {$redisPort}:6379 " .
                "--restart unless-stopped " .
                "redis:alpine " .
                "redis-server --appendonly yes 2>&1";
            
            Log::info('Creating Redis container: ' . $containerName);
            
            $output = shell_exec($command);
            
            if (strpos($output, 'Error') !== false) {
                throw new \Exception('Docker error: ' . $output);
            }
            
            return [
                'success' => true,
                'name' => $containerName,
                'port' => $redisPort,
                'output' => $output
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create Redis container: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create Docker network
     */
    private function createNetworkIfNotExists($networkName)
    {
        $check = shell_exec("docker network ls --filter name={$networkName} --format '{{.Name}}' 2>&1");
        if (empty(trim($check))) {
            $output = shell_exec("docker network create {$networkName} 2>&1");
            Log::info("Created network: {$networkName}");
            return true;
        }
        return false;
    }

    /**
     * Check if container exists
     */
    public function containerExists($containerName)
    {
        if (empty($containerName)) return false;
        
        $command = "docker ps -a --format '{{.Names}}' | findstr " . escapeshellarg($containerName) . " 2>&1";
        $output = shell_exec($command);
        return !empty(trim($output));
    }

    /**
     * Get container status
     */
    public function getContainerStatus($containerName)
    {
        if (empty($containerName)) return 'not_found';
        
        $command = "docker inspect --format='{{.State.Status}}' " . escapeshellarg($containerName) . " 2>&1";
        $status = trim(shell_exec($command));
        
        if (empty($status) || strpos($status, 'Error') !== false) {
            return 'not_found';
        }
        
        return $status;
    }

    /**
     * Stop container
     */
    public function stopContainer($containerName)
    {
        if (empty($containerName)) return false;
        if (!$this->containerExists($containerName)) return true;
        
        Log::info('Stopping container: ' . $containerName);
        $command = "docker stop " . escapeshellarg($containerName) . " 2>&1";
        shell_exec($command);
        
        return true;
    }

    /**
     * Start container
     */
    public function startContainer($containerName)
    {
        if (empty($containerName)) return false;
        if (!$this->containerExists($containerName)) return false;
        
        Log::info('Starting container: ' . $containerName);
        $command = "docker start " . escapeshellarg($containerName) . " 2>&1";
        shell_exec($command);
        
        return true;
    }

    /**
     * Remove container
     */
    public function removeContainer($containerName, $force = false)
    {
        if (empty($containerName)) return true;
        if (!$this->containerExists($containerName)) return true;
        
        Log::info('Removing container: ' . $containerName);
        
        $this->stopContainer($containerName);
        sleep(1);
        
        $forceFlag = $force ? ' -f' : '';
        $command = "docker rm{$forceFlag} " . escapeshellarg($containerName) . " 2>&1";
        shell_exec($command);
        
        return true;
    }

    /**
     * Get container logs
     */
    public function getLogs($containerName, $lines = 100)
    {
        if (empty($containerName)) return '';
        
        $command = "docker logs --tail {$lines} " . escapeshellarg($containerName) . " 2>&1";
        return shell_exec($command);
    }

    /**
     * Get container stats
     */
    public function getStats($containerName)
    {
        if (empty($containerName) || !$this->containerExists($containerName)) {
            return null;
        }
        
        $command = "docker stats --no-stream --format '{{.CPUPerc}}|{{.MemUsage}}|{{.NetIO}}|{{.BlockIO}}|{{.PIDs}}' " . escapeshellarg($containerName) . " 2>&1";
        $output = shell_exec($command);
        
        if (empty($output) || strpos($output, 'Error') !== false) {
            return null;
        }
        
        $parts = explode('|', trim($output));
        
        return [
            'CPU' => $parts[0] ?? '0%',
            'Memory' => $parts[1] ?? '0B / 0B',
            'NetIO' => $parts[2] ?? '0B / 0B',
            'BlockIO' => $parts[3] ?? '0B / 0B',
            'PIDs' => $parts[4] ?? '0'
        ];
    }
}