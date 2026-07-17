<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('customer_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
            $table->string('status', 30)->default('pending')->after('password')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'status']);
        });
    }
};
