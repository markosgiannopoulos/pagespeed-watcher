<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('watcher_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('watcher_pages')->cascadeOnDelete();
            $table->enum('strategy', ['mobile', 'desktop']);
            $table->unsignedTinyInteger('performance_score')->nullable();
            $table->unsignedInteger('lcp')->nullable();
            $table->unsignedInteger('inp')->nullable();
            $table->decimal('cls', 6, 3)->nullable();
            $table->unsignedInteger('fcp')->nullable();
            $table->unsignedInteger('tbt')->nullable();
            $table->unsignedInteger('speed_index')->nullable();
            $table->longText('raw_json')->nullable();
            $table->text('error_message')->nullable();
            $table->enum('status', ['ok', 'error'])->default('ok');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['page_id', 'strategy', 'created_at']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watcher_test_results');
    }
};