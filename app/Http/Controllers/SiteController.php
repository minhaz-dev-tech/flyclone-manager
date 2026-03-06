<?php
// app/Http/Controllers/SiteController.php

namespace App\Http\Controllers;

use App\Services\DockerService;
use App\Services\DomainService;
use Illuminate\Http\Request;
use App\Models\SiteStat;
use App\Models\Database;
use App\Models\Site;
use App\Models\RedisInstance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SiteController extends Controller
{
    protected $docker;
    protected $domain;

    public function __construct(DockerService $docker, DomainService $domain)
    {
        $this->docker = $docker;
        $this->domain = $domain;
    }

    /**
     * Get list of sites (users see only their own, admins see all)
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // Build query based on user role
            $query = Site::with(['latestStat', 'database', 'redis'])->latest();
            
            // If not admin, filter by user_id
            if ($user->role !== 'admin') {
                $query->where('user_id', $user->id);
            }
            
            $sites = $query->get();
            
            $enhancedSites = $sites->map(function ($site) use ($user) {
                // Get current stats from Docker if running
                $stats = null;
                if ($site->status === 'running' && $this->docker->containerExists($site->container_id)) {
                    $dockerStats = $this->docker->getStats($site->container_id);
                    if (!empty($dockerStats)) {
                        $stats = [
                            'cpu' => $dockerStats['CPU'] ?? '0%',
                            'memory' => $dockerStats['Memory'] ?? '0B / 0B',
                            'memory_usage' => $this->parseMemoryUsage($dockerStats['Memory'] ?? '0B / 0B'),
                            'net_io' => $dockerStats['NetIO'] ?? '0B / 0B',
                            'block_io' => $dockerStats['BlockIO'] ?? '0B / 0B',
                            'pids' => $dockerStats['PIDs'] ?? '0'
                        ];
                    }
                }

                $siteData = [
                    'id' => $site->id,
                    'name' => $site->name,
                    'domain' => $site->domain,
                    'custom_domain' => $site->custom_domain,
                    'domain_type' => $site->domain_type,
                    'port' => $site->port,
                    'status' => $site->status,
                    'container_name' => $site->container_id,
                    'ssl_enabled' => $site->ssl_enabled,
                    'protocol' => $site->protocol,
                    'wordpress_url' => $site->wordpress_url,
                    'admin_url' => $site->admin_url,
                    'mysql_port' => $site->mysql_port,
                    'redis_port' => $site->redis_port,
                    'created_at' => $site->created_at->toISOString(),
                    'updated_at' => $site->updated_at->toISOString(),
                    'stats' => $stats,
                    'database' => $site->database ? [
                        'id' => $site->database->id,
                        'name' => $site->database->name,
                        'status' => $site->database->status,
                        'port' => $site->database->port,
                        'container_name' => $site->database->container_name,
                        'database_name' => $site->database->database_name
                    ] : null,
                    'redis' => $site->redis ? [
                        'id' => $site->redis->id,
                        'name' => $site->redis->name,
                        'status' => $site->redis->status,
                        'port' => $site->redis->port,
                        'container_name' => $site->redis->container_name
                    ] : null
                ];
                
                // Add user info for admin view
                if ($user->role === 'admin' && $site->user) {
                    $siteData['user'] = [
                        'id' => $site->user->id,
                        'name' => $site->user->name,
                        'email' => $site->user->email
                    ];
                }
                
                return $siteData;
            });

            return response()->json([
                'success' => true,
                'data' => $enhancedSites,
                'meta' => [
                    'total' => $sites->count(),
                    'role' => $user->role,
                    'user_id' => $user->id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch sites: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sites: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single site (users see only their own, admins see all)
     */
    public function show(Request $request, Site $site)
    {
        try {
            $user = $request->user();
            
            // Check if user has permission to view this site
            if ($user->role !== 'admin' && $site->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this site'
                ], 403);
            }
            
            $site->load(['database', 'redis', 'latestStat', 'user']);
            
            // Get current Docker stats
            $dockerStats = null;
            if ($site->status === 'running' && $this->docker->containerExists($site->container_id)) {
                $dockerStats = $this->docker->getStats($site->container_id);
            }
            
            $responseData = [
                'id' => $site->id,
                'name' => $site->name,
                'domain' => $site->domain,
                'custom_domain' => $site->custom_domain,
                'domain_type' => $site->domain_type,
                'port' => $site->port,
                'status' => $site->status,
                'container_name' => $site->container_id,
                'ssl_enabled' => $site->ssl_enabled,
                'protocol' => $site->protocol,
                'wordpress_url' => $site->wordpress_url,
                'admin_url' => $site->admin_url,
                'mysql_port' => $site->mysql_port,
                'redis_port' => $site->redis_port,
                'created_at' => $site->created_at->toISOString(),
                'updated_at' => $site->updated_at->toISOString(),
                'database' => $site->database,
                'redis' => $site->redis,
                'current_stats' => $dockerStats,
                'latest_stats' => $site->latestStat
            ];
            
            // Add user info for admin view
            if ($user->role === 'admin' && $site->user) {
                $responseData['user'] = [
                    'id' => $site->user->id,
                    'name' => $site->user->name,
                    'email' => $site->user->email
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch site: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch site: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new WordPress site
     */


public function create(Request $request)
{
    $request->validate([
        'name' => 'required|string|regex:/^[a-z0-9-]+$/|unique:sites,name',
        'domain_type' => 'required|in:subdomain,custom',
        'custom_domain' => 'nullable|string|unique:sites,custom_domain',
        'enable_redis' => 'boolean',
        'enable_ssl' => 'boolean'
    ]);

    try {

        DB::beginTransaction();

        $siteName = $request->input('name');

        // ---------------- DOMAIN ----------------
        if ($request->domain_type === 'subdomain') {
            $domain = $this->domain->generateSubdomain($siteName, 8081);
            $customDomain = null;
        } else {
            $domain = $request->custom_domain;
            $customDomain = $request->custom_domain;
        }

        // ---------------- MYSQL ----------------
        Log::info("Creating MySQL container for {$siteName}");

        $mysqlResult = $this->docker->createMySQL($siteName, 3306);

        $mysqlContainerName = $mysqlResult['name'];

        sleep(8);

        // ---------------- WORDPRESS ----------------

        Log::info("Creating WordPress container for {$siteName}");

        $wordpressResult = $this->docker->createWordPress(
            $siteName,
            $domain,
            $request->enable_ssl ?? false,
            $mysqlContainerName
        );

        $wordpressContainerName = $wordpressResult['container'];
        $wordpressPort = $wordpressResult['host_port'];

        // ---------------- REDIS ----------------

        $redisContainerName = null;

        if ($request->enable_redis) {

            Log::info("Creating Redis container for {$siteName}");

            $redisResult = $this->docker->createRedis($siteName, 6379);

            $redisContainerName = $redisResult['name'];
        }

        // ---------------- SITE DB RECORD ----------------

        $site = Site::create([
            'name' => $siteName,
            'domain' => $domain,
            'custom_domain' => $customDomain,
            'domain_type' => $request->domain_type,
            'port' => $wordpressPort,
            'status' => 'running',
            'container_id' => $wordpressContainerName,
            'ssl_enabled' => $request->enable_ssl ?? false,
            'protocol' => $request->enable_ssl ? 'https' : 'http',
            'user_id' => $request->user()->id ?? null,
        ]);

        // ---------------- MYSQL DB RECORD ----------------

        Database::create([
            'site_id' => $site->id,
            'name' => $siteName . '_db',
            'status' => 'connected',
            'type' => 'mysql',
            'container_name' => $mysqlContainerName,
            'port' => 3306,
            'database_name' => $mysqlResult['database'],
            'username' => $mysqlResult['username'],
            'password' => bcrypt($mysqlResult['password']),
        ]);

        // ---------------- REDIS RECORD ----------------

        if ($redisContainerName) {

            RedisInstance::create([
                'site_id' => $site->id,
                'name' => $siteName . '_redis',
                'status' => 'cached',
                'type' => 'redis',
                'container_name' => $redisContainerName,
                'port' => 6379,
            ]);
        }

        // ---------------- STATS ----------------

        SiteStat::create([
            'site_id' => $site->id,
            'cpu' => 0,
            'memory' => 0,
            'disk' => 0,
            'requests' => 0,
            'recorded_at' => now(),
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'WordPress site created successfully',
            'data' => [
                'id' => $site->id,
                'name' => $site->name,
                'domain' => $site->domain,
                'port' => $wordpressPort,
                'wordpress_url' => $site->wordpress_url,
                'admin_url' => $site->admin_url,
                'mysql' => [
                    'container' => $mysqlContainerName,
                    'database' => $mysqlResult['database'],
                    'username' => $mysqlResult['username'],
                    'password' => $mysqlResult['password'],
                    'port' => 3306
                ],
                'wordpress' => [
                    'container' => $wordpressContainerName,
                    'port' => $wordpressPort
                ],
                'redis' => $redisContainerName ? [
                    'container' => $redisContainerName,
                    'port' => 6379
                ] : null
            ]
        ], 201);

    } catch (\Exception $e) {

        DB::rollBack();

        Log::error('Site creation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to create WordPress site: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Update site (users update only their own, admins update all)
     */
    public function update(Request $request, Site $site)
    {
        $user = $request->user();
        
        // Check if user has permission to update this site
        if ($user->role !== 'admin' && $site->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this site'
            ], 403);
        }
        
        $request->validate([
            'name' => 'sometimes|string|regex:/^[a-z0-9-]+$/|unique:sites,name,' . $site->id,
            'status' => 'sometimes|in:running,stopped,pending',
        ]);

        try {
            $site->update($request->only(['name', 'status']));

            return response()->json([
                'success' => true,
                'message' => 'Site updated successfully',
                'data' => $site
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update site: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update site: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a site and all its associated containers
     */
    public function destroy(Request $request, Site $site)
    {
        $user = $request->user();
        
        // Check if user has permission to delete this site
        if ($user->role !== 'admin' && $site->user_id !== $user->id) {
            Log::warning('Unauthorized delete attempt', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'site_id' => $site->id,
                'site_owner' => $site->user_id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this site'
            ], 403);
        }
        
        try {
            DB::beginTransaction();

            Log::info('Starting deletion process for site: ' . $site->name, [
                'site_id' => $site->id,
                'wordpress_container' => $site->container_id,
                'mysql_container' => $site->database?->container_name,
                'redis_container' => $site->redis?->container_name
            ]);

            $deletedContainers = [];
            $failedContainers = [];

            // Stop all containers first
            $containersToStop = [];
            if (!empty($site->container_id)) {
                $containersToStop[] = $site->container_id;
            }
            if ($site->database && !empty($site->database->container_name)) {
                $containersToStop[] = $site->database->container_name;
            }
            if ($site->redis && !empty($site->redis->container_name)) {
                $containersToStop[] = $site->redis->container_name;
            }
            
            foreach ($containersToStop as $container) {
                try {
                    if ($this->docker->containerExists($container)) {
                        $this->docker->stopContainer($container);
                        Log::info('Stopped container: ' . $container);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to stop container: ' . $container . ' - ' . $e->getMessage());
                }
            }

            // Wait a moment for containers to stop
            sleep(2);

            // ==================== REMOVE DOCKER CONTAINERS ====================
            
            // 1. Remove WordPress container
            if (!empty($site->container_id)) {
                if ($this->docker->containerExists($site->container_id)) {
                    if ($this->docker->removeContainer($site->container_id, true)) {
                        $deletedContainers[] = $site->container_id;
                        Log::info('Removed WordPress container: ' . $site->container_id);
                    } else {
                        $failedContainers[] = $site->container_id;
                        Log::error('Failed to remove WordPress container: ' . $site->container_id);
                    }
                } else {
                    Log::info('WordPress container does not exist: ' . $site->container_id);
                }
            }

            // 2. Remove MySQL container
            if ($site->database && !empty($site->database->container_name)) {
                if ($this->docker->containerExists($site->database->container_name)) {
                    if ($this->docker->removeContainer($site->database->container_name, true)) {
                        $deletedContainers[] = $site->database->container_name;
                        Log::info('Removed MySQL container: ' . $site->database->container_name);
                    } else {
                        $failedContainers[] = $site->database->container_name;
                        Log::error('Failed to remove MySQL container: ' . $site->database->container_name);
                    }
                } else {
                    Log::info('MySQL container does not exist: ' . $site->database->container_name);
                }
            }

            // 3. Remove Redis container
            if ($site->redis && !empty($site->redis->container_name)) {
                if ($this->docker->containerExists($site->redis->container_name)) {
                    if ($this->docker->removeContainer($site->redis->container_name, true)) {
                        $deletedContainers[] = $site->redis->container_name;
                        Log::info('Removed Redis container: ' . $site->redis->container_name);
                    } else {
                        $failedContainers[] = $site->redis->container_name;
                        Log::error('Failed to remove Redis container: ' . $site->redis->container_name);
                    }
                } else {
                    Log::info('Redis container does not exist: ' . $site->redis->container_name);
                }
            }

            // 4. Also try to find and remove any containers that might match the site name pattern
            $orphanedRemoved = $this->cleanupOrphanedContainersByPattern($site->name);
            if (!empty($orphanedRemoved)) {
                $deletedContainers = array_merge($deletedContainers, $orphanedRemoved);
                Log::info('Removed orphaned containers: ' . implode(', ', $orphanedRemoved));
            }

            // ==================== DELETE DATABASE RECORDS ====================
            
            // Delete stats first
            $statsDeleted = SiteStat::where('site_id', $site->id)->delete();
            Log::info('Deleted stats: ' . $statsDeleted);

            // Delete redis instance if exists
            if ($site->redis) {
                $site->redis()->delete();
                Log::info('Deleted redis record');
            }

            // Delete database record
            if ($site->database) {
                $site->database()->delete();
                Log::info('Deleted database record');
            }

            // Save site info before deletion
            $siteId = $site->id;
            $siteName = $site->name;

            // Finally delete the site
            $site->delete();
            Log::info('Site record deleted', ['id' => $siteId, 'name' => $siteName]);

            DB::commit();

            $message = 'Site and all associated containers deleted successfully';
            if (!empty($failedContainers)) {
                $message = 'Site database deleted but some containers could not be removed: ' . implode(', ', $failedContainers);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'site_id' => $siteId,
                    'site_name' => $siteName,
                    'deleted_containers' => $deletedContainers,
                    'failed_containers' => $failedContainers
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete site: ' . $e->getMessage(), [
                'site_id' => $site->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete site: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up any orphaned containers that match the site name pattern
     */
    private function cleanupOrphanedContainersByPattern($siteName)
    {
        $removedContainers = [];
        
        try {
            // Get all Docker containers
            $command = "docker ps -a --format '{{.Names}}'";
            $output = shell_exec($command);
            
            if (empty($output)) {
                return $removedContainers;
            }
            
            $allContainers = array_filter(explode("\n", trim($output)));

            // Pattern to match: sitename_wp_, sitename_db_, sitename_redis_
            $pattern = '/' . preg_quote($siteName, '/') . '_(wp|db|redis)_[0-9]+/';
            
            foreach ($allContainers as $container) {
                $container = trim($container);
                if (empty($container)) continue;
                
                // Check if container name matches the pattern
                if (preg_match($pattern, $container)) {
                    Log::info('Found orphaned container matching pattern: ' . $container);
                    
                    try {
                        // Stop the container first
                        $this->docker->stopContainer($container);
                        
                        // Then remove it
                        if ($this->docker->removeContainer($container, true)) {
                            $removedContainers[] = $container;
                            Log::info('Removed orphaned container: ' . $container);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to remove orphaned container: ' . $container . ' - ' . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error during orphaned container cleanup: ' . $e->getMessage());
        }
        
        return $removedContainers;
    }

    /**
     * Start a site
     */
    public function start(Request $request, Site $site)
    {
        $user = $request->user();
        
        // Check if user has permission to start this site
        if ($user->role !== 'admin' && $site->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to start this site'
            ], 403);
        }
        
        try {
            $started = [];
            $failed = [];

            // Start WordPress container
            if ($this->docker->startContainer($site->container_id)) {
                $started[] = $site->container_id;
            } else {
                $failed[] = $site->container_id;
            }
            
            // Start MySQL container
            if ($site->database && $this->docker->startContainer($site->database->container_name)) {
                $started[] = $site->database->container_name;
            } else if ($site->database) {
                $failed[] = $site->database->container_name;
            }
            
            // Start Redis container if exists
            if ($site->redis && $this->docker->startContainer($site->redis->container_name)) {
                $started[] = $site->redis->container_name;
            } else if ($site->redis) {
                $failed[] = $site->redis->container_name;
            }
            
            // Update site status if at least WordPress started
            if (in_array($site->container_id, $started)) {
                $site->update(['status' => 'running']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Site start attempted',
                'data' => [
                    'id' => $site->id,
                    'status' => $site->status,
                    'started_containers' => $started,
                    'failed_containers' => $failed
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start site: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to start site: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stop a site
     */
    public function stop(Request $request, Site $site)
    {
        $user = $request->user();
        
        // Check if user has permission to stop this site
        if ($user->role !== 'admin' && $site->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to stop this site'
            ], 403);
        }
        
        try {
            $stopped = [];
            $failed = [];

            // Stop WordPress container
            if ($this->docker->stopContainer($site->container_id)) {
                $stopped[] = $site->container_id;
            } else {
                $failed[] = $site->container_id;
            }
            
            // Stop MySQL container
            if ($site->database && $this->docker->stopContainer($site->database->container_name)) {
                $stopped[] = $site->database->container_name;
            } else if ($site->database) {
                $failed[] = $site->database->container_name;
            }
            
            // Stop Redis container if exists
            if ($site->redis && $this->docker->stopContainer($site->redis->container_name)) {
                $stopped[] = $site->redis->container_name;
            } else if ($site->redis) {
                $failed[] = $site->redis->container_name;
            }
            
            // Update site status if WordPress stopped
            $site->update(['status' => 'stopped']);

            return response()->json([
                'success' => true,
                'message' => 'Site stop attempted',
                'data' => [
                    'id' => $site->id,
                    'status' => $site->status,
                    'stopped_containers' => $stopped,
                    'failed_containers' => $failed
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to stop site: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to stop site: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restart a site
     */
    public function restart(Request $request, Site $site)
    {
        $user = $request->user();
        
        // Check if user has permission to restart this site
        if ($user->role !== 'admin' && $site->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to restart this site'
            ], 403);
        }
        
        try {
            $restarted = [];
            $failed = [];

            // Restart WordPress container
            if ($this->docker->restartContainer($site->container_id)) {
                $restarted[] = $site->container_id;
            } else {
                $failed[] = $site->container_id;
            }
            
            // Restart MySQL container
            if ($site->database && $this->docker->restartContainer($site->database->container_name)) {
                $restarted[] = $site->database->container_name;
            } else if ($site->database) {
                $failed[] = $site->database->container_name;
            }
            
            // Restart Redis container if exists
            if ($site->redis && $this->docker->restartContainer($site->redis->container_name)) {
                $restarted[] = $site->redis->container_name;
            } else if ($site->redis) {
                $failed[] = $site->redis->container_name;
            }
            
            $site->update(['status' => 'running']);

            return response()->json([
                'success' => true,
                'message' => 'Site restarted',
                'data' => [
                    'id' => $site->id,
                    'status' => $site->status,
                    'restarted_containers' => $restarted,
                    'failed_containers' => $failed
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to restart site: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to restart site: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update site domain
     */
    public function updateDomain(Request $request, Site $site)
    {
        $user = $request->user();
        
        // Check if user has permission to update this site's domain
        if ($user->role !== 'admin' && $site->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this site'
            ], 403);
        }
        
        $request->validate([
            'domain_type' => 'required|in:subdomain,custom',
            'custom_domain' => 'required_if:domain_type,custom|nullable|string|unique:sites,custom_domain,' . $site->id
        ]);

        try {
            $oldUrl = $site->wordpress_url;
            
            if ($request->domain_type === 'subdomain') {
                $newDomain = $this->domain->generateSubdomain($site->name, $site->port);
                $customDomain = null;
            } else {
                $newDomain = $request->custom_domain;
                $customDomain = $request->custom_domain;
                
                $verification = $this->domain->verifyDomainPointsToServer($customDomain);
                if (!$verification['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => $verification['message']
                    ], 400);
                }
            }

            // Update WordPress configuration
            $this->docker->configureDomain(
                $site->container_id, 
                $newDomain, 
                $site->port,
                $site->ssl_enabled
            );

            // Update SSL if needed
            if ($site->ssl_enabled) {
                $this->domain->setupSSL($newDomain, $site->port);
            }

            // Update database
            $site->update([
                'domain' => $newDomain,
                'custom_domain' => $customDomain,
                'domain_type' => $request->domain_type
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Domain updated successfully',
                'data' => [
                    'old_url' => $oldUrl,
                    'new_url' => $site->wordpress_url,
                    'domain' => $newDomain,
                    'domain_type' => $request->domain_type
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update domain: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update domain: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enable SSL for site
     */
    public function enableSSL(Request $request, Site $site)
    {
        $user = $request->user();
        
        // Check if user has permission to enable SSL
        if ($user->role !== 'admin' && $site->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to modify this site'
            ], 403);
        }
        
        try {
            $this->domain->setupSSL($site->domain, $site->port);
            
            $site->update([
                'ssl_enabled' => true,
                'protocol' => 'https'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'SSL enabled successfully',
                'data' => [
                    'url' => $site->wordpress_url
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to enable SSL: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to enable SSL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disable SSL for site
     */
    public function disableSSL(Request $request, Site $site)
    {
        $user = $request->user();
        
        // Check if user has permission to disable SSL
        if ($user->role !== 'admin' && $site->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to modify this site'
            ], 403);
        }
        
        try {
            $site->update([
                'ssl_enabled' => false,
                'protocol' => 'http'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'SSL disabled successfully',
                'data' => [
                    'url' => $site->wordpress_url
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to disable SSL: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to disable SSL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check domain availability
     */
    public function checkDomain(Request $request)
    {
        $request->validate([
            'domain' => 'required|string'
        ]);

        try {
            $domain = $request->domain;
            
            // Check if domain exists in database
            $exists = Site::where('domain', $domain)
                ->orWhere('custom_domain', $domain)
                ->exists();

            // Verify DNS
            $verification = $this->domain->verifyDomainPointsToServer($domain);

            return response()->json([
                'success' => true,
                'data' => [
                    'domain' => $domain,
                    'available' => !$exists,
                    'dns_verified' => $verification['success'],
                    'message' => $this->getDomainMessage($exists, $verification),
                    'dns_details' => $verification
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check domain: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check domain: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get site stats
     */
    public function stats(Request $request, Site $site)
    {
        $user = $request->user();
        
        // Check if user has permission to view stats for this site
        if ($user->role !== 'admin' && $site->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view stats for this site'
            ], 403);
        }
        
        try {
            $stats = [
                'wordpress' => $this->docker->getStats($site->container_id),
                'mysql' => $site->database ? $this->docker->getStats($site->database->container_name) : null,
                'redis' => $site->redis ? $this->docker->getStats($site->redis->container_name) : null
            ];

            // Save to database if WordPress stats available
            if (!empty($stats['wordpress'])) {
                $memoryUsage = $this->parseMemoryUsage($stats['wordpress']['Memory'] ?? '0B / 0B');
                $cpuValue = floatval(str_replace('%', '', $stats['wordpress']['CPU'] ?? '0'));
                
                SiteStat::create([
                    'site_id' => $site->id,
                    'cpu' => $cpuValue,
                    'memory' => round($memoryUsage['used'] / 1024 / 1024, 2),
                    'disk' => 0,
                    'requests' => 0,
                    'recorded_at' => now()
                ]);
            }

            // Get historical stats
            $historicalStats = $site->stats()
                ->latest('recorded_at')
                ->take(60)
                ->get()
                ->map(function ($stat) {
                    return [
                        'time' => $stat->recorded_at->format('H:i'),
                        'cpu' => $stat->cpu,
                        'memory' => $stat->memory
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'site_id' => $site->id,
                    'site_name' => $site->name,
                    'current_stats' => $stats,
                    'historical' => $historicalStats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stats for all sites
     */
    public function allStats(Request $request)
    {
        try {
            $user = $request->user();
            
            $query = Site::with('latestStat');
            
            // If not admin, only show their sites
            if ($user->role !== 'admin') {
                $query->where('user_id', $user->id);
            }
            
            $sites = $query->get();
            $allStats = [];

            foreach ($sites as $site) {
                $latestStat = $site->latestStat;
                
                $allStats[] = [
                    'site_id' => $site->id,
                    'site_name' => $site->name,
                    'user_id' => $site->user_id,
                    'status' => $site->status,
                    'current' => [
                        'cpu' => $latestStat?->cpu ?? 0,
                        'memory' => $latestStat?->memory ?? 0,
                        'disk' => $latestStat?->disk ?? 0,
                        'requests' => $latestStat?->requests ?? 0,
                        'updated_at' => $latestStat?->recorded_at?->toISOString()
                    ]
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $allStats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get all stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get all stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get site logs
     */
    public function logs(Request $request, Site $site)
    {
        $user = $request->user();
        
        // Check if user has permission to view logs for this site
        if ($user->role !== 'admin' && $site->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view logs for this site'
            ], 403);
        }
        
        try {
            $logs = $this->docker->getLogs($site->container_id, 100);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'site_id' => $site->id,
                    'logs' => $logs
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up old stats
     */
    public function cleanupStats(Request $request)
    {
        // Only admins can cleanup stats
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        try {
            $deleted = SiteStat::where('recorded_at', '<', now()->subDays(7))->delete();
            
            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$deleted} old stat records"
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cleanup stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug Docker info for a site
     */
    public function dockerInfo(Request $request, Site $site)
    {
        $user = $request->user();
        
        // Only admins can view debug info
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        try {
            $info = [
                'site' => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'wordpress_container' => $site->container_id,
                    'mysql_container' => $site->database?->container_name,
                    'redis_container' => $site->redis?->container_name,
                ],
                'docker' => [
                    'wordpress' => [
                        'name' => $site->container_id,
                        'exists' => $this->docker->containerExists($site->container_id),
                        'status' => $this->docker->getContainerStatus($site->container_id)
                    ]
                ]
            ];

            // Check MySQL container
            if ($site->database) {
                $info['docker']['mysql'] = [
                    'name' => $site->database->container_name,
                    'exists' => $this->docker->containerExists($site->database->container_name),
                    'status' => $this->docker->getContainerStatus($site->database->container_name)
                ];
            }

            // Check Redis container
            if ($site->redis) {
                $info['docker']['redis'] = [
                    'name' => $site->redis->container_name,
                    'exists' => $this->docker->containerExists($site->redis->container_name),
                    'status' => $this->docker->getContainerStatus($site->redis->container_name)
                ];
            }

            // Also check for any containers matching the pattern
            $pattern = $site->name . '_';
            $command = "docker ps -a --format '{{.Names}}' | grep " . escapeshellarg($pattern);
            $output = shell_exec($command);
            $matchingContainers = array_filter(explode("\n", trim($output)));
            
            $info['matching_containers'] = $matchingContainers;

            return response()->json([
                'success' => true,
                'data' => $info
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test site installation
     */
    public function testSite(Request $request, $id)
    {
        $user = $request->user();
        $site = Site::find($id);
        
        if (!$site) {
            return response()->json(['error' => 'Site not found'], 404);
        }
        
        // Check permission
        if ($user->role !== 'admin' && $site->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $tests = [
                'containers' => [
                    'wordpress' => [
                        'exists' => $this->docker->containerExists($site->container_id),
                        'status' => $this->docker->getContainerStatus($site->container_id)
                    ],
                    'mysql' => [
                        'exists' => $site->database ? $this->docker->containerExists($site->database->container_name) : false,
                        'status' => $site->database ? $this->docker->getContainerStatus($site->database->container_name) : null
                    ],
                    'redis' => $site->redis ? [
                        'exists' => $this->docker->containerExists($site->redis->container_name),
                        'status' => $this->docker->getContainerStatus($site->redis->container_name)
                    ] : null
                ],
                'wordpress_url' => $site->wordpress_url,
                'logs' => [
                    'wordpress' => $this->docker->getLogs($site->container_id, 20),
                    'mysql' => $site->database ? $this->docker->getLogs($site->database->container_name, 20) : null
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $tests
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a random password
     */
    private function generateRandomPassword($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+';
        return substr(str_shuffle($chars), 0, $length);
    }

    /**
     * Helper function to parse memory usage
     */
    private function parseMemoryUsage($memoryString)
    {
        if (!$memoryString) return ['used' => 0, 'total' => 0, 'percentage' => 0];
        
        $parts = explode(' / ', $memoryString);
        $used = $this->convertToBytes($parts[0] ?? '0B');
        $total = $this->convertToBytes($parts[1] ?? '0B');
        
        return [
            'used' => $used,
            'total' => $total,
            'percentage' => $total > 0 ? round(($used / $total) * 100, 1) : 0
        ];
    }

    /**
     * Helper function to convert memory string to bytes
     */
    private function convertToBytes($size)
    {
        $size = trim($size);
        
        if (strpos($size, 'MiB') !== false) {
            return (float) str_replace('MiB', '', $size) * 1024 * 1024;
        }
        if (strpos($size, 'GiB') !== false) {
            return (float) str_replace('GiB', '', $size) * 1024 * 1024 * 1024;
        }
        
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
     * Get domain message helper
     */
    private function getDomainMessage($exists, $verification)
    {
        if ($exists) {
            return 'This domain is already in use';
        }
        if (!$verification['success']) {
            return $verification['message'] ?? 'DNS verification failed. Make sure your domain points to this server.';
        }
        return 'Domain is available and verified';
    }

    /**
     * Force delete a site (admin only)
     */
    public function forceDelete(Request $request, $id)
    {
        $user = $request->user();
        
        // Only admins can force delete
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can force delete sites'
            ], 403);
        }
        
        try {
            DB::beginTransaction();
            
            $site = Site::withTrashed()->find($id);
            
            if (!$site) {
                return response()->json([
                    'success' => false,
                    'message' => 'Site not found'
                ], 404);
            }
            
            Log::warning('Force deleting site: ' . $site->name, [
                'site_id' => $site->id,
                'user_id' => $user->id
            ]);
            
            // Try to remove containers but don't fail if they don't exist
            $containers = [
                $site->container_id,
                $site->database?->container_name,
                $site->redis?->container_name
            ];
            
            foreach ($containers as $container) {
                if (!empty($container) && $this->docker->containerExists($container)) {
                    $this->docker->stopContainer($container);
                    $this->docker->removeContainer($container, true);
                }
            }
            
            // Delete all related records
            SiteStat::where('site_id', $site->id)->delete();
            Database::where('site_id', $site->id)->delete();
            RedisInstance::where('site_id', $site->id)->delete();
            
            // Force delete the site
            $site->forceDelete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Site force deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to force delete site: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to force delete site: ' . $e->getMessage()
            ], 500);
        }
    }
}