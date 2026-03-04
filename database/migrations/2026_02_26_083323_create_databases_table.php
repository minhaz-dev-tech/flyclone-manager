// database/migrations/2024_01_01_000003_create_databases_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('status', ['connected', 'disconnected', 'error'])->default('connected');
            $table->string('type')->default('mysql');
            $table->string('container_name');
            $table->integer('port');
            $table->string('database_name');
            $table->string('username');
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('databases');
    }
};