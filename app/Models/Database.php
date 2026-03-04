<?php
// app/Models/Database.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Database extends Model
{
    use HasFactory;

    protected $table = 'databases';

    protected $fillable = [
        'site_id',
        'name',
        'status',
        'type',
        'container_name',
        'port',
        'database_name',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'port' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'connection_string',
        'status_color'
    ];

    /**
     * Get the site that owns the database
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get connection string
     */
    public function getConnectionStringAttribute(): string
    {
        return "mysql://{$this->username}:******@localhost:{$this->port}/{$this->database_name}";
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'connected' => 'green',
            'disconnected' => 'gray',
            'error' => 'red',
            default => 'gray'
        };
    }

    /**
     * Check if database is connected
     */
    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    /**
     * Scope for connected databases
     */
    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }
}