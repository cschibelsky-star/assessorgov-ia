<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_compliance_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('customer_id')->index();
            $table->string('item_key', 160);
            $table->string('title');
            $table->text('action');
            $table->string('priority', 20)->default('medium')->index();
            $table->string('impact_class', 40)->nullable();
            $table->string('target', 120)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->text('note')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->uuid('applied_by_user_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'item_key']);
        });

        Schema::create('gov_compliance_item_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gov_compliance_item_id')
                ->constrained('gov_compliance_items')
                ->cascadeOnDelete();
            $table->foreignId('customer_opportunity_id')
                ->constrained('customer_opportunities')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['gov_compliance_item_id', 'customer_opportunity_id'], 'gov_comp_item_opp_unique');
        });

        Schema::create('gov_compliance_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gov_compliance_item_id')
                ->constrained('gov_compliance_items')
                ->cascadeOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path', 2048);
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->string('sha256', 64)->nullable()->index();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->uuid('uploaded_by_user_id')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });

        $this->backfillLegacyJson();
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_compliance_evidences');
        Schema::dropIfExists('gov_compliance_item_opportunities');
        Schema::dropIfExists('gov_compliance_items');
    }

    private function backfillLegacyJson(): void
    {
        DB::table('customer_opportunities')
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $metadata = json_decode((string) $row->metadata, true);

                    if (! is_array($metadata)) {
                        continue;
                    }

                    $actions = $metadata['gov_intelligence_actions'] ?? null;

                    if (! is_array($actions)) {
                        continue;
                    }

                    foreach ($actions as $key => $action) {
                        if (! is_array($action)) {
                            continue;
                        }

                        $itemKey = (string) ($action['id'] ?? $key);

                        if ($itemKey === '') {
                            continue;
                        }

                        $itemId = DB::table('gov_compliance_items')
                            ->where('customer_id', (string) $row->customer_id)
                            ->where('item_key', $itemKey)
                            ->value('id');

                        $payload = [
                            'customer_id' => (string) $row->customer_id,
                            'item_key' => $itemKey,
                            'title' => (string) ($action['title'] ?? $itemKey),
                            'action' => (string) ($action['action'] ?? ''),
                            'priority' => (string) ($action['priority'] ?? 'medium'),
                            'impact_class' => $action['impact_class'] ?? null,
                            'target' => $action['target'] ?? null,
                            'status' => (string) ($action['status'] ?? 'pending'),
                            'note' => $action['note'] ?? null,
                            'applied_at' => $this->dateValue($action['applied_at'] ?? null),
                            'applied_by_user_id' => $action['applied_by_user_id'] ?? null,
                            'submitted_at' => $this->dateValue($action['submitted_at'] ?? null),
                            'resolved_at' => $this->dateValue($action['resolved_at'] ?? null),
                            'updated_by_user_id' => $action['updated_by_user_id'] ?? null,
                            'updated_at' => now(),
                        ];

                        if ($itemId) {
                            DB::table('gov_compliance_items')->where('id', $itemId)->update($payload);
                        } else {
                            $payload['created_at'] = now();
                            $itemId = DB::table('gov_compliance_items')->insertGetId($payload);
                        }

                        DB::table('gov_compliance_item_opportunities')->updateOrInsert(
                            [
                                'gov_compliance_item_id' => $itemId,
                                'customer_opportunity_id' => $row->id,
                            ],
                            [
                                'updated_at' => now(),
                                'created_at' => now(),
                            ],
                        );

                        $evidence = $action['evidence'] ?? null;

                        if (is_array($evidence) && ! empty($evidence['path'])) {
                            $exists = DB::table('gov_compliance_evidences')
                                ->where('gov_compliance_item_id', $itemId)
                                ->where('path', $evidence['path'])
                                ->exists();

                            if (! $exists) {
                                DB::table('gov_compliance_evidences')->insert([
                                    'gov_compliance_item_id' => $itemId,
                                    'disk' => $evidence['disk'] ?? 'local',
                                    'path' => $evidence['path'],
                                    'original_name' => $evidence['original_name'] ?? basename($evidence['path']),
                                    'mime_type' => $evidence['mime_type'] ?? null,
                                    'sha256' => null,
                                    'size_bytes' => null,
                                    'uploaded_by_user_id' => $evidence['uploaded_by_user_id'] ?? null,
                                    'uploaded_at' => $this->dateValue($evidence['uploaded_at'] ?? null),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    }
                }
            });
    }

    private function dateValue(mixed $value): mixed
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
};
