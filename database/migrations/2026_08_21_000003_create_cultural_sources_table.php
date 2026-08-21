<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cultural_sources', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('owner');
            $table->string('scope', 30)->default('state');
            $table->string('state', 2)->default('SP');
            $table->string('municipality')->nullable();
            $table->text('url');
            $table->string('source_type', 30)->default('official_web');
            $table->string('ingestion_mode', 30)->default('html');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('enabled')->default(true)->index();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_status', 40)->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cultural_sources');
    }
};
