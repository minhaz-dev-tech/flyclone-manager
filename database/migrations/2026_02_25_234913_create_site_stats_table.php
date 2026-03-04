<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
     public function up(): void
    {
        Schema::create('site_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->float('cpu')->default(0);
            $table->float('memory')->default(0);
            $table->float('disk')->default(0);
            $table->integer('requests')->default(0);
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            // Index for faster queries
            $table->index(['site_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_stats');
    }
};