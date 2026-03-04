// database/migrations/2024_01_01_000004_create_redis_instances_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('redis_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('status', ['cached', 'idle', 'error'])->default('idle');
            $table->string('type')->default('redis');
            $table->string('container_name');
            $table->integer('port');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('redis_instances');
    }
};