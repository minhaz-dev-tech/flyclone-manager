// database/migrations/2024_01_01_000002_create_sites_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('domain');
            $table->enum('domain_type', ['subdomain', 'custom'])->default('subdomain');
            $table->string('custom_domain')->nullable();
            $table->integer('port')->unique();
            $table->enum('status', ['running', 'stopped', 'pending'])->default('pending');
            $table->string('container_name');
            $table->boolean('ssl_enabled')->default(false);
            $table->enum('protocol', ['http', 'https'])->default('http');
            $table->integer('mysql_port')->nullable();
            $table->integer('redis_port')->nullable();
            $table->json('stats')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sites');
    }
};