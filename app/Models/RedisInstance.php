<?php
// app/Models/RedisInstance.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedisInstance extends Model
{
    use HasFactory;

    protected $table = 'redis_instances';

    protected $fillable = [
        'site_id',
        'name',
        'status',
        'type',
        'container_name',
        'port',
    ];

    protected $casts = [
        'port' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'status_color',
        'is_active'
    ];

    /**
     * Get the site that owns the redis instance
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'cached' => 'green',
            'idle' => 'gray',
            'error' => 'red',
            default => 'gray'
        };
    }

    /**
     * Check if redis is active
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'cached';
    }

    /**
     * Check if redis is active (method version)
     */
    public function isActive(): bool
    {
        return $this->status === 'cached';
    }

    /**
     * Scope for active redis instances
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'cached');
    }
}