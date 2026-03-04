<?php
// app/Models/Site.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Site extends Model
{
    use HasFactory;

    protected $table = 'sites';

    protected $fillable = [
        'user_id',
        'name',
        'domain',
        'custom_domain',
        'domain_type',
        'port',
        'status',
        'container_id',
        'ssl_enabled',
        'protocol',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'port' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'wordpress_url',
        'admin_url',
        'status_color',
        'mysql_port',
        'redis_port'
    ];

    /**
     * Get the user that owns the site
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the database associated with the site
     */
    public function database(): HasOne
    {
        return $this->hasOne(Database::class, 'site_id');
    }

    /**
     * Get the redis instance associated with the site
     */
    public function redis(): HasOne
    {
        return $this->hasOne(RedisInstance::class, 'site_id');
    }

    /**
     * Get the stats for the site
     */
    public function stats(): HasMany
    {
        return $this->hasMany(SiteStat::class, 'site_id');
    }

    /**
     * Get the latest stat for the site
     */
    public function latestStat(): HasOne
    {
        return $this->hasOne(SiteStat::class, 'site_id')->latest('recorded_at');
    }

    /**
     * Get daily stats for the site
     */
    public function dailyStats()
    {
        return $this->hasMany(SiteStat::class, 'site_id')
                    ->where('recorded_at', '>=', now()->subDay())
                    ->orderBy('recorded_at');
    }

    /**
     * Get weekly stats for the site
     */
    public function weeklyStats()
    {
        return $this->hasMany(SiteStat::class, 'site_id')
                    ->where('recorded_at', '>=', now()->subWeek())
                    ->orderBy('recorded_at');
    }

    /**
     * Get monthly stats for the site
     */
    public function monthlyStats()
    {
        return $this->hasMany(SiteStat::class, 'site_id')
                    ->where('recorded_at', '>=', now()->subMonth())
                    ->orderBy('recorded_at');
    }

    /**
     * Get all backups for the site
     */
    public function backups()
    {
        // If you have a backups table/relationship
        return $this->hasMany(Backup::class, 'site_id');
    }

    /**
     * Get full WordPress URL
     */
    public function getWordpressUrlAttribute(): string
    {
        return "{$this->protocol}://{$this->domain}:{$this->port}";
    }

    /**
     * Get admin URL
     */
    public function getAdminUrlAttribute(): string
    {
        return $this->wordpress_url . '/wp-admin';
    }

    /**
     * Get MySQL port
     */
    public function getMysqlPortAttribute(): int
    {
        return $this->port + 1000;
    }

    /**
     * Get Redis port
     */
    public function getRedisPortAttribute(): ?int
    {
        return $this->redis ? $this->port + 2000 : null;
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'running' => 'green',
            'stopped' => 'gray',
            'pending' => 'yellow',
            'error' => 'red',
            default => 'gray'
        };
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'running' => 'bg-green-500/10 text-green-500',
            'stopped' => 'bg-gray-500/10 text-gray-500',
            'pending' => 'bg-yellow-500/10 text-yellow-500 animate-pulse',
            'error' => 'bg-red-500/10 text-red-500',
            default => 'bg-gray-500/10 text-gray-500'
        };
    }

    /**
     * Check if site is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if site has Redis enabled
     */
    public function hasRedis(): bool
    {
        return $this->redis !== null;
    }

    /**
     * Check if SSL is enabled
     */
    public function hasSsl(): bool
    {
        return $this->ssl_enabled === true;
    }

    /**
     * Get container names
     */
    public function getWordPressContainerAttribute(): string
    {
        return $this->container_id;
    }

    public function getMysqlContainerAttribute(): string
    {
        return $this->container_id . '_db';
    }

    public function getRedisContainerAttribute(): ?string
    {
        return $this->hasRedis() ? $this->container_id . '_redis' : null;
    }

    /**
     * Scope for running sites
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope for stopped sites
     */
    public function scopeStopped($query)
    {
        return $query->where('status', 'stopped');
    }

    /**
     * Scope for pending sites
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for sites with Redis enabled
     */
    public function scopeWithRedis($query)
    {
        return $query->whereHas('redis');
    }

    /**
     * Scope for sites with SSL enabled
     */
    public function scopeWithSsl($query)
    {
        return $query->where('ssl_enabled', true);
    }

    /**
     * Scope for sites by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for subdomain sites
     */
    public function scopeSubdomain($query)
    {
        return $query->where('domain_type', 'subdomain');
    }

    /**
     * Scope for custom domain sites
     */
    public function scopeCustomDomain($query)
    {
        return $query->where('domain_type', 'custom');
    }

    /**
     * Get the site summary
     */
    public function getSummaryAttribute(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'status' => $this->status,
            'url' => $this->wordpress_url,
            'has_redis' => $this->hasRedis(),
            'has_ssl' => $this->hasSsl(),
            'containers' => [
                'wordpress' => $this->wordpress_container,
                'mysql' => $this->mysql_container,
                'redis' => $this->redis_container
            ],
            'ports' => [
                'wordpress' => $this->port,
                'mysql' => $this->mysql_port,
                'redis' => $this->redis_port
            ]
        ];
    }

    /**
     * Get detailed site information
     */
    public function getDetailsAttribute(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => [
                'primary' => $this->domain,
                'custom' => $this->custom_domain,
                'type' => $this->domain_type
            ],
            'status' => [
                'current' => $this->status,
                'color' => $this->status_color,
                'badge' => $this->status_badge_class
            ],
            'urls' => [
                'site' => $this->wordpress_url,
                'admin' => $this->admin_url
            ],
            'containers' => [
                'wordpress' => [
                    'name' => $this->wordpress_container,
                    'port' => $this->port,
                    'status' => $this->status
                ],
                'mysql' => [
                    'name' => $this->mysql_container,
                    'port' => $this->mysql_port,
                    'status' => $this->database?->status ?? 'unknown'
                ],
                'redis' => $this->hasRedis() ? [
                    'name' => $this->redis_container,
                    'port' => $this->redis_port,
                    'status' => $this->redis?->status ?? 'unknown'
                ] : null
            ],
            'features' => [
                'ssl' => $this->hasSsl(),
                'redis' => $this->hasRedis()
            ],
            'database' => $this->database ? [
                'name' => $this->database->database_name,
                'username' => $this->database->username,
                'port' => $this->database->port,
                'status' => $this->database->status
            ] : null,
            'redis' => $this->redis ? [
                'name' => $this->redis->name,
                'port' => $this->redis->port,
                'status' => $this->redis->status
            ] : null,
            'timestamps' => [
                'created' => $this->created_at?->toISOString(),
                'updated' => $this->updated_at?->toISOString()
            ]
        ];
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // When site is deleted, clean up related records
        static::deleting(function ($site) {
            // Delete related records
            $site->database()->delete();
            $site->redis()->delete();
            $site->stats()->delete();
        });

        // After site is created
        static::created(function ($site) {
            // Log site creation or perform other tasks
            \Log::info('New site created', ['site_id' => $site->id, 'name' => $site->name]);
        });
    }
}