<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 40)->default('detected')->index();
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->json('match_reasons')->nullable();
            $table->json('gaps')->nullable();
            $table->json('strategy')->nullable();
            $table->string('decision', 30)->nullable()->index();
            $table->unsignedInteger('classification_position')->nullable();
            $table->boolean('remanescente_eligible')->default(false)->index();
            $table->string('execution_status', 40)->nullable()->index();
            $table->string('financial_status', 40)->nullable()->index();
            $table->decimal('contracted_value', 16, 2)->nullable();
            $table->decimal('billed_value', 16, 2)->nullable();
            $table->decimal('paid_value', 16, 2)->nullable();
            $table->timestamp('last_financial_sync_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'opportunity_id']);
            $table->index(['customer_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_opportunities');
    }
};
