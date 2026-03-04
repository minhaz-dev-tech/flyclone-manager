<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DomainService
{
    protected $baseDomain;
    protected $serverIp;
    protected $isLocal;

    public function __construct()
    {
        $this->isLocal = app()->environment('local');
        $this->baseDomain = $this->isLocal ? 'localhost' : (config('app.base_domain') ?? 'yourdomain.com');
        $this->serverIp = $this->getServerIp();
    }

    /**
     * Generate subdomain based on site name and environment
     */
    public function generateSubdomain(string $siteName, int $port): string
    {
        // Clean the site name
        $subdomain = preg_replace('/[^a-z0-9-]/', '', strtolower($siteName));
        $subdomain = substr($subdomain, 0, 63);
        
        if ($this->isLocal) {
            // Local: sitename.localhost
            return "{$subdomain}.localhost";
        }
        
        // Production: sitename.flyclonemanager.com
        return "{$subdomain}.flyclonemanager.com";
    }

    /**
     * Get full URL with proper protocol
     */

    /**
     * Get full URL with port for local development
     */
     public function getFullUrl(string $domain, int $port, bool $ssl = false): string
    {
        $protocol = $ssl ? 'https' : 'http';
        
        if ($this->isLocal) {
            // Local with port: http://sitename.localhost:8081
            return "{$protocol}://{$domain}:{$port}";
        }
        
        if ($ssl) {
            // Production with SSL: https://sitename.flyclonemanager.com
            return "https://{$domain}";
        }
        
        // Production without SSL: http://sitename.flyclonemanager.com
        return "http://{$domain}";
    }

    /**
     * Setup SSL for subdomain using Let's Encrypt
     */

    /**
     * Verify domain points to server (for custom domains)
     */
    public function verifyDomainPointsToServer(string $domain): array
    {
        if ($this->isLocal) {
            return $this->verifyLocalDomain($domain);
        }

        try {
            // Get DNS A records
            $dnsRecords = dns_get_record($domain, DNS_A);
            
            if (empty($dnsRecords)) {
                return [
                    'success' => false,
                    'message' => 'No DNS A records found for this domain',
                    'records_found' => []
                ];
            }

            $matches = [];
            $serverIp = $this->getServerIp();
            
            foreach ($dnsRecords as $record) {
                if (isset($record['ip']) && $record['ip'] === $serverIp) {
                    $matches[] = $record;
                }
            }
            
            return [
                'success' => count($matches) > 0,
                'message' => count($matches) > 0 
                    ? "Domain verified successfully (points to {$serverIp})"
                    : "Domain does not point to our server ({$serverIp})",
                'records_found' => $dnsRecords,
                'matches' => $matches,
                'server_ip' => $serverIp
            ];
            
        } catch (\Exception $e) {
            Log::error('DNS verification failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'DNS verification failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify local domain (check hosts file)
     */
    protected function verifyLocalDomain(string $domain): array
    {
        // Remove port if present
        $host = parse_url($domain, PHP_URL_HOST) ?: $domain;
        
        // Check if domain ends with localhost or .local
        if (str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || $host === 'localhost') {
            // For local development, we'll check if it's in hosts file by pinging
            $pingable = $this->pingDomain($host);
            
            if ($pingable) {
                return [
                    'success' => true,
                    'message' => "Local domain '{$host}' is valid and reachable",
                    'host' => $host,
                    'note' => 'Domain resolves to localhost'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Local domain '{$host}' is not in hosts file",
                    'host' => $host,
                    'note' => 'Add this to your hosts file: 127.0.0.1 ' . $host,
                    'instructions' => $this->getHostsFileInstructions($host)
                ];
            }
        }

        // Try to ping anyway
        $pingable = $this->pingDomain($host);
        
        return [
            'success' => $pingable,
            'message' => $pingable 
                ? "Domain '{$host}' is reachable locally"
                : "Domain '{$host}' not found. Check your hosts file",
            'host' => $host,
            'instructions' => $pingable ? null : $this->getHostsFileInstructions($host)
        ];
    }

    /**
     * Get hosts file instructions based on OS
     */
    protected function getHostsFileInstructions(string $host): string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return "Run as Administrator: echo 127.0.0.1 {$host} >> C:\\Windows\\System32\\drivers\\etc\\hosts";
        } else {
            return "Run: sudo sh -c 'echo \"127.0.0.1 {$host}\" >> /etc/hosts'";
        }
    }

    /**
     * Check domain availability in database
     */
    public function checkDomainAvailability(string $domain): array
    {
        $siteModel = app(\App\Models\Site::class);
        
        $exists = $siteModel->where('domain', $domain)
            ->orWhere('custom_domain', $domain)
            ->exists();

        return [
            'available' => !$exists,
            'domain' => $domain,
            'message' => !$exists ? 'Domain is available' : 'Domain is already in use'
        ];
    }

    /**
     * Setup SSL (Let's Encrypt for production, simulated for local)
     */
  public function setupSSL(string $domain, int $port): array
    {
        if ($this->isLocal) {
            // Local SSL simulation
            return [
                'success' => true,
                'message' => "Local SSL simulated for {$domain}",
                'url' => "https://{$domain}:{$port}"
            ];
        }

        try {
            // For flyclonemanager.com subdomains
            // Check if certbot is installed
            exec("which certbot", $certbotCheck, $checkCode);
            
            if ($checkCode !== 0) {
                return [
                    'success' => false,
                    'message' => 'Certbot is not installed. Install with: sudo apt-get install certbot python3-certbot-nginx'
                ];
            }

            // Get wildcard certificate for *.flyclonemanager.com
            // or specific subdomain certificate
            $email = config('app.ssl_email') ?? 'admin@flyclonemanager.com';
            
            // For wildcard (covers all subdomains)
            if (str_contains($domain, 'flyclonemanager.com')) {
                $command = "certbot certonly --nginx -d *.flyclonemanager.com -d flyclonemanager.com --non-interactive --agree-tos --email {$email} 2>&1";
            } else {
                // For specific subdomain
                $command = "certbot --nginx -d {$domain} --non-interactive --agree-tos --email {$email} 2>&1";
            }
            
            exec($command, $output, $returnCode);
            
            $success = $returnCode === 0;
            
            if ($success) {
                Log::info("SSL certificate installed for {$domain}");
                
                // Configure Nginx to use SSL
                $this->configureNginxSSL($domain, $port);
                
                return [
                    'success' => true,
                    'message' => "SSL enabled for {$domain}",
                    'url' => "https://{$domain}"
                ];
            } else {
                Log::error("SSL installation failed for {$domain}");
                return [
                    'success' => false,
                    'message' => 'SSL installation failed',
                    'output' => $output
                ];
            }
            
        } catch (\Exception $e) {
            Log::error('SSL setup failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'SSL setup failed: ' . $e->getMessage()
            ];
        }
    }


    /**
     * Setup local SSL (simulated for testing)
     */
    protected function setupLocalSSL(string $domain, int $port): array
    {
        Log::info("Local SSL simulated for {$domain}:{$port}");
        
        return [
            'success' => true,
            'message' => "SSL simulated for local development",
            'note' => 'In production, this would install a real SSL certificate',
            'url' => "https://{$domain}:{$port}"
        ];
    }

    /**
     * Disable SSL for a domain
     */
    public function disableSSL(string $domain): array
    {
        if ($this->isLocal) {
            return [
                'success' => true,
                'message' => "SSL disabled for {$domain} (local simulation)"
            ];
        }

        try {
            $command = "certbot delete --cert-name {$domain} --non-interactive 2>&1";
            exec($command, $output, $returnCode);
            
            $success = $returnCode === 0;
            
            return [
                'success' => $success,
                'message' => $success ? "SSL certificate removed for {$domain}" : "Failed to remove SSL certificate",
                'output' => $output
            ];
            
        } catch (\Exception $e) {
            Log::error('SSL disable failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'SSL disable failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if domain has SSL
     */
    public function hasSSL(string $domain): bool
    {
        if ($this->isLocal) {
            return false; // Local doesn't have real SSL by default
        }

        // Check if certificate exists
        $command = "certbot certificates --cert-name {$domain} 2>/dev/null | grep -q 'Certificate Name: {$domain}'";
        exec($command, $output, $returnCode);
        
        return $returnCode === 0;
    }

    /**
     * Renew SSL certificate
     */
    public function renewSSL(string $domain): array
    {
        if ($this->isLocal) {
            return [
                'success' => true,
                'message' => "SSL renewal simulated for {$domain}"
            ];
        }

        try {
            $command = "certbot renew --cert-name {$domain} --non-interactive 2>&1";
            exec($command, $output, $returnCode);
            
            return [
                'success' => $returnCode === 0,
                'message' => $returnCode === 0 ? "SSL renewed for {$domain}" : "SSL renewal failed",
                'output' => $output
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'SSL renewal failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Ping a domain to check if it's reachable
     */
    protected function pingDomain(string $domain): bool
    {
        $output = [];
        $returnCode = 0;
        
        // Remove protocol if present
        $host = preg_replace('#^https?://#', '', $domain);
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            exec("ping -n 1 -w 1000 " . escapeshellarg($host), $output, $returnCode);
        } else {
            // Linux/Mac
            exec("ping -c 1 -W 1 " . escapeshellarg($host) . " > /dev/null 2>&1", $output, $returnCode);
        }
        
        return $returnCode === 0;
    }

    /**
     * Get server IP address
     */
     protected function getServerIp(): string
    {
        $ip = env('SERVER_IP');
        
        if ($ip) {
            return $ip;
        }

        try {
            $response = Http::get('https://api.ipify.org?format=json');
            return $response->json()['ip'] ?? '127.0.0.1';
        } catch (\Exception $e) {
            return '127.0.0.1';
        }
    }

    /**
     * Get base domain
     */
    public function getBaseDomain(): string
    {
        return $this->baseDomain;
    }

    /**
     * Check if running in local environment
     */
    public function isLocal(): bool
    {
        return $this->isLocal;
    }

    /**
     * Validate domain format
     */
    public function validateDomainFormat(string $domain): bool
    {
        // Basic domain validation
        if ($this->isLocal) {
            // Allow local domains
            return preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.(localhost|local|test)$/', $domain) === 1;
        }
        
        // Production domain validation
        return preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}$/', $domain) === 1;
    }

    /**
     * Extract domain from URL
     */
    public function extractDomain(string $url): string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? $url;
    }

    /**
     * Get domain without port
     */
    public function getDomainWithoutPort(string $domain): string
    {
        return preg_replace('/:\d+$/', '', $domain);
    }
      protected function configureNginxSSL(string $domain, int $port): bool
    {
        // Extract subdomain from full domain
        $parts = explode('.', $domain);
        $subdomain = $parts[0];
        
        // Nginx config for subdomain
        $config = "
server {
    listen 80;
    server_name {$domain};
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name {$domain};
    
    ssl_certificate /etc/letsencrypt/live/{$domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{$domain}/privkey.pem;
    
    location / {
        proxy_pass http://localhost:{$port};
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}";
        
        // Save config
        $configPath = "/etc/nginx/sites-available/{$domain}";
        file_put_contents($configPath, $config);
        
        // Enable site
        exec("ln -s {$configPath} /etc/nginx/sites-enabled/ 2>/dev/null");
        
        // Test and reload Nginx
        exec("nginx -t", $testOutput, $testCode);
        if ($testCode === 0) {
            exec("systemctl reload nginx");
            return true;
        }
        
        return false;
    }

}

