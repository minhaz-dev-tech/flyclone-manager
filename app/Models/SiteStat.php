<?php
// app/Models/SiteStat.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteStat extends Model
{
    use HasFactory;

    protected $table = 'site_stats';

    protected $fillable = [
        'site_id',
        'cpu',
        'memory',
        'disk',
        'requests',
        'recorded_at'
    ];

    protected $casts = [
        'cpu' => 'float',
        'memory' => 'float',
        'disk' => 'float',
        'requests' => 'integer',
        'recorded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'memory_formatted',
        'cpu_formatted'
    ];

    /**
     * Get the site that owns the stats
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get formatted memory
     */
    public function getMemoryFormattedAttribute(): string
    {
        return $this->formatBytes($this->memory * 1024 * 1024);
    }

    /**
     * Get formatted CPU
     */
    public function getCpuFormattedAttribute(): string
    {
        return $this->cpu . '%';
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . $units[$pow];
    }

    /**
     * Scope for latest stats
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('recorded_at', 'desc');
    }

    /**
     * Scope for stats within a date range
     */
    public function scopeWithinDays($query, $days)
    {
        return $query->where('recorded_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for stats between dates
     */
    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }
}