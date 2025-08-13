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
            $table->string('strategy', 8); // 'mobile' | 'desktop' – validate in code
            $table->unsignedTinyInteger('performance_score')->nullable();
            $table->unsignedInteger('lcp_ms')->nullable();
            $table->unsignedInteger('inp_ms')->nullable();
            $table->decimal('cls', 6, 4)->nullable();
            $table->unsignedInteger('fcp_ms')->nullable();
            $table->unsignedInteger('tbt_ms')->nullable();
            $table->unsignedInteger('speed_index_ms')->nullable();
            $table->json('raw_json')->nullable();
            $table->text('error_message')->nullable();
            $table->string('status', 5)->default('ok'); // validate in code
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['page_id', 'strategy', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watcher_test_results');
    }
};