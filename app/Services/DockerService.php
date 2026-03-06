<?php
// app/Services/DockerService.php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DockerService
{
    /**
     * Create WordPress container
     */

    /**
 * Get a free host port starting from $startPort
 */
private function getFreePort($startPort = 8081)
{
    $port = $startPort;
    while (true) {
        // Check if any container is using this port
        $output = shell_exec("docker ps --format '{{.Ports}}' | grep -w {$port}");
        if (empty(trim($output))) {
            return $port;
        }
        $port++;
        if ($port > 65535) {
            throw new \Exception('No free ports available.');
        }
    }
}
public function createWordPress($name, $domain, $ssl = false, $mysqlContainer = null, $hostPort = null)
{
    try {
        $siteName = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        $timestamp = now()->timestamp;
        $containerName = $siteName . '_wp_' . $timestamp;

        if (!$mysqlContainer) {
            $mysqlContainer = $siteName . '_db_' . $timestamp;
        }

        $dbName = 'wordpress_' . $siteName;
        $dbUser = 'wp_' . $siteName;
        $dbPassword = 'password'; // simple password

        $this->createNetworkIfNotExists('wpnetwork_' . $siteName);

        // If port not provided, get a free one
        if (!$hostPort) {
            $hostPort = $this->getFreePort(8081);
        }

        Log::info("Creating WordPress container: {$containerName} on port {$hostPort}");
        Log::info("Linking to MySQL: {$mysqlContainer}");

        $command = "docker run -d " .
            "--name {$containerName} " .
            "--network wpnetwork_{$siteName} " .
            "-p {$hostPort}:80 " .
            "-e WORDPRESS_DB_HOST={$mysqlContainer}:3306 " .
            "-e WORDPRESS_DB_USER={$dbUser} " .
            "-e WORDPRESS_DB_PASSWORD={$dbPassword} " .
            "-e WORDPRESS_DB_NAME={$dbName} " .
            "--restart unless-stopped wordpress:latest 2>&1";

        $output = shell_exec($command);

        sleep(5); // wait for WP to initialize

        // ---------------- CREATE VHOST ----------------
      if (PHP_OS_FAMILY === 'Linux') {
    $this->createVirtualHost($domain, $hostPort, $ssl ?? false);
} else {
    Log::info("Skipping virtual host creation: not running on Linux.");
}

        return [
            'success' => true,
            'container' => $containerName,
            'host_port' => $hostPort,
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
     * Create MySQL container
     */
    public function createMySQL($name, $port)
    {
        try {
            $siteName = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
            $timestamp = now()->timestamp;
            $containerName = $siteName . '_db_' . $timestamp;
            $mysqlPort = $port + 1000;

            $dbName = 'wordpress_' . $siteName;
            $dbUser = 'wp_' . $siteName;
            $dbPassword = 'password';
            $rootPassword = 'root';

            $this->createNetworkIfNotExists('wpnetwork_' . $siteName);
            $this->removeContainer($containerName);

            Log::info("Creating MySQL container: {$containerName}");

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
            $ready = $this->waitForMySQL($containerName, 60);

            if (!$ready) {
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
            $status = $this->getContainerStatus($containerName);
            if ($status !== 'running') {
                if ($i % 10 == 0) Log::warning("MySQL container status: {$status}, waiting...");
                sleep(1);
                continue;
            }

            $testCommand = "docker exec {$containerName} mysqladmin ping -h localhost --silent 2>&1";
            $result = shell_exec($testCommand);

            if (strpos($result, 'mysqld is alive') !== false) {
                Log::info("MySQL is ready after {$i} seconds");
                return true;
            }

            if ($i % 10 == 0) Log::info("Still waiting for MySQL... ({$i}/{$maxAttempts})");
            sleep(1);
        }

        Log::warning("MySQL may not be fully ready, but continuing...");
        return false;
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
     * Create Apache virtual host dynamically
     */
public function createVirtualHost($domain, $port)
{
    try {
        $confFile = preg_replace('/[^a-zA-Z0-9_.-]/', '', $domain) . '.conf';
        $sitesAvailable = '/etc/apache2/sites-available';
        $fullPath = "{$sitesAvailable}/{$confFile}";

        // Use your existing SSL certificate and key
        $certPath = "/home/minhaj/my-php-site/certs/{$domain}.crt";
        $keyPath  = "/home/minhaj/my-php-site/certs/{$domain}.key";

        // VirtualHost content for both HTTP and HTTPS
        $vhost = "
<VirtualHost *:80>
    ServerName {$domain}
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:{$port}/
    ProxyPassReverse / http://127.0.0.1:{$port}/
    ErrorLog \${APACHE_LOG_DIR}/{$domain}_error.log
    CustomLog \${APACHE_LOG_DIR}/{$domain}_access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName {$domain}
    SSLEngine on
    SSLCertificateFile {$certPath}
    SSLCertificateKeyFile {$keyPath}
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:{$port}/
    ProxyPassReverse / http://127.0.0.1:{$port}/
    ErrorLog \${APACHE_LOG_DIR}/{$domain}_ssl_error.log
    CustomLog \${APACHE_LOG_DIR}/{$domain}_ssl_access.log combined
</VirtualHost>
";

        file_put_contents($fullPath, $vhost);

        // Enable site and reload Apache
        shell_exec("a2ensite {$confFile} && systemctl reload apache2");

        return true;
    } catch (\Exception $e) {
        Log::error("Failed to create virtual host for {$domain}: " . $e->getMessage());
        throw $e;
    }
}

    /**
     * Create Docker network if not exists
     */
    private function createNetworkIfNotExists($networkName)
    {
        $check = shell_exec("docker network ls --filter name={$networkName} --format '{{.Name}}' 2>&1");
        if (empty(trim($check))) {
            shell_exec("docker network create {$networkName} 2>&1");
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
        $output = shell_exec("docker ps -a --format '{{.Names}}' | findstr " . escapeshellarg($containerName));
        return !empty(trim($output));
    }

    /**
     * Get container status
     */
    public function getContainerStatus($containerName)
    {
        if (empty($containerName)) return 'not_found';
        $status = trim(shell_exec("docker inspect --format='{{.State.Status}}' " . escapeshellarg($containerName)));
        if (empty($status) || strpos($status, 'Error') !== false) return 'not_found';
        return $status;
    }

    /**
     * Stop container
     */
    public function stopContainer($containerName)
    {
        if (empty($containerName) || !$this->containerExists($containerName)) return true;
        shell_exec("docker stop " . escapeshellarg($containerName));
        return true;
    }

    /**
     * Start container
     */
    public function startContainer($containerName)
    {
        if (empty($containerName) || !$this->containerExists($containerName)) return false;
        shell_exec("docker start " . escapeshellarg($containerName));
        return true;
    }

    /**
     * Remove container
     */
    public function removeContainer($containerName, $force = false)
    {
        if (empty($containerName) || !$this->containerExists($containerName)) return true;
        $this->stopContainer($containerName);
        sleep(1);
        $forceFlag = $force ? ' -f' : '';
        shell_exec("docker rm{$forceFlag} " . escapeshellarg($containerName));
        return true;
    }

    /**
     * Get container logs
     */
    public function getLogs($containerName, $lines = 100)
    {
        if (empty($containerName)) return '';
        return shell_exec("docker logs --tail {$lines} " . escapeshellarg($containerName));
    }

    /**
     * Get container stats
     */
    public function getStats($containerName)
    {
        if (empty($containerName) || !$this->containerExists($containerName)) return null;
        $output = shell_exec("docker stats --no-stream --format '{{.CPUPerc}}|{{.MemUsage}}|{{.NetIO}}|{{.BlockIO}}|{{.PIDs}}' " . escapeshellarg($containerName));
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