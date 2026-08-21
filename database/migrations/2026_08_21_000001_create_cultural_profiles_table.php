<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cultural_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->string('document_type', 30)->nullable();
            $table->string('municipality')->nullable();
            $table->string('state', 2)->default('SP');
            $table->json('cultural_areas')->nullable();
            $table->json('legal_profiles')->nullable();
            $table->json('territories')->nullable();
            $table->unsignedSmallInteger('experience_years')->nullable();
            $table->decimal('preferred_budget_min', 14, 2)->nullable();
            $table->decimal('preferred_budget_max', 14, 2)->nullable();
            $table->json('audiences')->nullable();
            $table->json('accessibility_experience')->nullable();
            $table->boolean('profile_complete')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cultural_profiles');
    }
};
