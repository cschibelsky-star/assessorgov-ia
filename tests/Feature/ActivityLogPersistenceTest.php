<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_helper_persists_an_audit_record(): void
    {
        $activity = activity()
            ->event('audit_test')
            ->withProperties(['scope' => 'isolated-test'])
            ->log('Audit persistence verified');

        $this->assertNotNull($activity);
        $this->assertDatabaseHas(config('activitylog.table_name'), [
            'id' => $activity->getKey(),
            'description' => 'Audit persistence verified',
            'event' => 'audit_test',
        ]);
        $this->assertSame(['scope' => 'isolated-test'], $activity->properties->all());
    }
}
