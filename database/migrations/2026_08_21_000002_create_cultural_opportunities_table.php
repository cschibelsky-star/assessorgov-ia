<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cultural_opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('source_name');
            $table->string('source_type', 50)->default('official');
            $table->string('source_url', 2048);
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('organization')->nullable();
            $table->string('opportunity_type', 80)->nullable();
            $table->string('state', 2)->default('SP')->index();
            $table->json('municipalities')->nullable();
            $table->json('cultural_areas')->nullable();
            $table->json('eligible_legal_profiles')->nullable();
            $table->decimal('funding_min', 14, 2)->nullable();
            $table->decimal('funding_max', 14, 2)->nullable();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable()->index();
            $table->json('eligibility_rules')->nullable();
            $table->json('required_documents')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamp('source_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['source_name', 'external_id']);
            $table->index(['state', 'status', 'closes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cultural_opportunities');
    }
};
