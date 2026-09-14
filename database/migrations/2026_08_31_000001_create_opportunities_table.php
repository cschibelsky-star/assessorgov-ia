<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 40)->index();
            $table->string('source_name', 120);
            $table->string('source_type', 40)->default('official');
            $table->string('source_url', 2048);
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('organization')->nullable()->index();
            $table->string('jurisdiction', 120)->nullable();
            $table->string('state', 2)->nullable()->index();
            $table->json('municipalities')->nullable();
            $table->decimal('estimated_value', 16, 2)->nullable();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable()->index();
            $table->dateTime('event_at')->nullable();
            $table->json('requirements')->nullable();
            $table->json('required_documents')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamp('source_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['source_name', 'external_id']);
            $table->index(['channel', 'status', 'closes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
