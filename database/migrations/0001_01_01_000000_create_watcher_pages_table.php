<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('watcher_pages', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->boolean('mobile_enabled')->default(true);
            $table->boolean('desktop_enabled')->default(true);
            $table->unsignedTinyInteger('priority')->default(0);
            $table->boolean('auto_discovered')->default(false);
            $table->timestamps();

            $table->index(['active', 'priority']);
            $table->index('auto_discovered');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watcher_pages');
    }
};