<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('watcher_api_usage', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->integer('requests_total')->default(0);
            $table->integer('requests_ok')->default(0);
            $table->integer('requests_error')->default(0);
            $table->decimal('cost_usd_estimate', 10, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watcher_api_usage');
    }
};