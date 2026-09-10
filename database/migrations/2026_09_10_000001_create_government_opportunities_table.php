<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 50)->index();
            $table->string('source_module', 80)->index();
            $table->string('external_id', 191)->nullable();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->string('status', 50)->nullable()->index();
            $table->string('state', 2)->nullable()->index();
            $table->string('municipality')->nullable()->index();
            $table->string('organization')->nullable()->index();
            $table->string('instrument_type')->nullable()->index();
            $table->decimal('amount_min', 15, 2)->nullable();
            $table->decimal('amount_max', 15, 2)->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable()->index();
            $table->text('source_url')->nullable();
            $table->timestamp('fetched_at')->nullable()->index();
            $table->char('raw_payload_hash', 64)->index();
            $table->json('raw_payload');
            $table->timestamps();

            $table->unique(['source', 'source_module', 'external_id'], 'gov_opp_source_external_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_opportunities');
    }
};
