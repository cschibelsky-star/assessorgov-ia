<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('billing_status', 30)->default('pending')->index()->after('status');
            $table->string('operational_status', 30)->default('normal')->index()->after('billing_status');
            $table->timestamp('past_due_at')->nullable()->after('operational_status');
            $table->timestamp('grace_until')->nullable()->after('past_due_at');
            $table->timestamp('restricted_at')->nullable()->after('grace_until');
            $table->timestamp('preservation_at')->nullable()->after('restricted_at');
            $table->timestamp('suspended_at')->nullable()->after('preservation_at');
            $table->timestamp('reactivated_at')->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_status',
                'operational_status',
                'past_due_at',
                'grace_until',
                'restricted_at',
                'preservation_at',
                'suspended_at',
                'reactivated_at',
            ]);
        });
    }
};
